<?php

namespace App\Command;

use App\ValueObject\WarframeItemName;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:update-data',
    description: 'Updates local JSON data from Warframe API',
)]
class UpdateDataCommand extends Command
{
    private HttpClientInterface $httpClient;
    private \Meilisearch\Client $meiliClient;
    private string $dataDir;

    public function __construct(
        HttpClientInterface $httpClient,
        \Meilisearch\Client $meiliClient,
        #[Autowire('%kernel.project_dir%/data')] string $dataDir,
    ) {
        $this->httpClient = $httpClient;
        $this->meiliClient = $meiliClient;
        $this->dataDir = $dataDir;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '-1');
        try {
            $output->writeln('Fetching items from Warframe API...');

            $output->writeln('Fetching Global Drops (this may take a while)...');
            $dropsMap = $this->fetchDrops($output);

            if (empty($dropsMap)) {
                $output->writeln('Drops fetch failed. Falling back to local Relics_Normalized.json');
                $this->indexLocalFile('Relics_Normalized.json', 'relics', $output);
            } else {
                $output->writeln('Processing Relics...');
                $relicItems = $this->fetchCategory('Relics', $output);
                if (empty($relicItems)) {
                    $output->writeln('Relic items fetch failed. Falling back to local Relics_Normalized.json');
                    $this->indexLocalFile('Relics_Normalized.json', 'relics', $output);
                } else {
                    $this->processRelics($relicItems, $dropsMap, $output);
                }
            }

            $output->writeln('Processing Primes...');
            $primeCategories = ['Warframes', 'Primary', 'Secondary', 'Melee', 'Archwing', 'Sentinels', 'Pets', 'Skins'];
            $primeItems = [];

            foreach ($primeCategories as $cat) {
                gc_collect_cycles();
                $catItems = $this->fetchCategory($cat, $output);
                if (empty($catItems)) {
                    $output->writeln("Failed to fetch $cat");
                }
                $primeItems = array_merge($primeItems, $catItems);
            }

            if (empty($primeItems)) {
                $output->writeln('Prime items fetch failed (or empty). Falling back to local Primes_Normalized.json');
                $this->indexLocalFile('Primes_Normalized.json', 'primes', $output);
            } else {
                $this->processPrimes($primeItems, $output);
            }

            $output->writeln('Data update complete.');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('CRITICAL ERROR: '.$e->getMessage());
            $output->writeln($e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    private function indexLocalFile(string $filename, string $indexName, OutputInterface $output): void
    {
        $path = $this->dataDir.'/'.$filename;
        if (!file_exists($path)) {
            $output->writeln("Error: Local file $filename not found in $path. Cannot fallback.");

            return;
        }

        $data = json_decode(file_get_contents($path), true);
        if (!$data) {
            $output->writeln("Error: Failed to decode $filename.");

            return;
        }

        foreach ($data as &$item) {
            if (isset($item['slug'])) {
                $item['slug'] = preg_replace('/[^a-zA-Z0-9_-]/', '_', $item['slug']);
            }
        }
        unset($item);

        $index = $this->meiliClient->index($indexName);

        $task = $index->deleteAllDocuments();
        $this->meiliClient->waitForTask($task['taskUid']);

        $settings = match ($indexName) {
            'relics' => [
                'searchableAttributes' => ['name', 'rewards.item'],
                'filterableAttributes' => ['name'],
                'sortableAttributes' => ['name'],
            ],
            'primes' => [
                'searchableAttributes' => ['name', 'parts.name'],
                'filterableAttributes' => ['name', 'slug'],
                'sortableAttributes' => ['name'],
            ],
            default => [],
        };

        if ($settings) {
            $task = $index->updateSettings($settings);
            $this->meiliClient->waitForTask($task['taskUid']);
        }

        $task = $index->addDocuments($data, 'slug');
        $this->meiliClient->waitForTask($task['taskUid']);

        $output->writeln(sprintf('Indexed %d documents from %s into %s.', count($data), $filename, $indexName));
    }

    private function fetchDrops(OutputInterface $output): array
    {
        $this->indexMissionRewards($output);

        try {
            $response = $this->httpClient->request('GET', 'https://api.warframestat.us/drops', [
                'timeout' => 120,
            ]);

            if (200 !== $response->getStatusCode()) {
                $output->writeln('   Drops fetch failed: '.$response->getStatusCode());

                return [];
            }

            $data = $response->toArray();
            $map = [];

            foreach ($data as $drop) {
                $place = $drop['place'] ?? '';
                if (!str_contains($place, 'Relic')) {
                    continue;
                }

                $basePlace = (new WarframeItemName($place))->getCleanName();

                if (!isset($map[$basePlace])) {
                    $map[$basePlace] = [];
                }

                $map[$basePlace][] = [
                    'item' => $drop['item'],
                    'rarity' => $drop['rarity'],
                    'chance' => $drop['chance'],
                ];
            }

            foreach ($map as $k => $drops) {
                $uniqueDrops = [];
                foreach ($drops as $d) {
                    $item = $d['item'];
                    if (!isset($uniqueDrops[$item])) {
                        $uniqueDrops[$item] = $d;
                    } else {
                        if ($d['chance'] > $uniqueDrops[$item]['chance']) {
                            $uniqueDrops[$item] = $d;
                        }
                    }
                }
                $map[$k] = array_values($uniqueDrops);
            }

            $output->writeln('   Fetched and processed '.count($map).' relic drop tables.');

            return $map;
        } catch (\Exception $e) {
            $output->writeln('   Error fetching drops: '.$e->getMessage());

            return [];
        }
    }

    private function indexMissionRewards(OutputInterface $output): void
    {
        $output->writeln('Fetching and Indexing Mission Rewards...');
        try {
            $response = $this->httpClient->request('GET', 'https://drops.warframestat.us/data/missionRewards.json', [
                'timeout' => 120,
            ]);

            $data = [];
            if (200 === $response->getStatusCode()) {
                $jsonData = $response->toArray();
                $data = $jsonData['missionRewards'] ?? [];
                file_put_contents($this->dataDir.'/missionRewards.json', json_encode($jsonData));
            } elseif (file_exists($this->dataDir.'/missionRewards.json')) {
                $output->writeln('   Failed to fetch missionRewards.json, using local backup.');
                $jsonData = json_decode(file_get_contents($this->dataDir.'/missionRewards.json'), true);
                $data = $jsonData['missionRewards'] ?? [];
            } else {
                $output->writeln('   Failed to fetch missionRewards.json and no local backup found.');

                return;
            }

            $indexData = [];
            foreach ($data as $planet => $missions) {
                if (!is_array($missions)) {
                    continue;
                }
                foreach ($missions as $mission => $missionData) {
                    $rewards = $missionData['rewards'] ?? [];
                    if (!is_array($rewards)) {
                        continue;
                    }
                    foreach ($rewards as $rotation => $items) {
                        if (!is_array($items)) {
                            continue;
                        }
                        foreach ($items as $item) {
                            $itemName = $item['itemName'] ?? '';
                            if (!$itemName) {
                                continue;
                            }

                            $slug = (new WarframeItemName($itemName))->getSlug();
                            $slug = preg_replace('/[^a-zA-Z0-9_-]/', '_', $slug);

                            if (!isset($indexData[$slug])) {
                                $indexData[$slug] = [
                                    'id' => $slug,
                                    'name' => $itemName,
                                    'locations' => [],
                                ];
                            }

                            $indexData[$slug]['locations'][] = [
                                'planet' => $planet,
                                'mission' => $mission,
                                'rotation' => $rotation,
                                'chance' => $item['chance'] ?? 0,
                                'gameMode' => $missionData['gameMode'] ?? 'Unknown',
                            ];
                        }
                    }
                }
            }

            $documents = array_values($indexData);

            $index = $this->meiliClient->index('mission_rewards');

            $task = $index->deleteAllDocuments();
            $this->meiliClient->waitForTask($task['taskUid']);

            $task = $index->updateSettings([
                'searchableAttributes' => ['name'],
                'filterableAttributes' => ['name'],
            ]);
            $this->meiliClient->waitForTask($task['taskUid']);

            $chunks = array_chunk($documents, 1000);
            foreach ($chunks as $chunk) {
                $task = $index->addDocuments($chunk, 'id');
                $this->meiliClient->waitForTask($task['taskUid']);
            }

            $output->writeln(sprintf('   Indexed mission rewards for %d items.', count($documents)));
        } catch (\Exception $e) {
            $output->writeln('   Error indexing mission rewards: '.$e->getMessage());
        }
    }

    private function fetchCategory(string $category, OutputInterface $output): array
    {
        $output->writeln(" - Fetching category: {$category} (query param)");

        try {
            $response = $this->httpClient->request('GET', 'https://api.warframestat.us/items', [
                'query' => [
                    'category' => $category,
                ],
                'timeout' => 60,
            ]);

            if (200 !== $response->getStatusCode()) {
                $output->writeln("   Request failed (Status {$response->getStatusCode()})");

                return [];
            }

            $data = $response->toArray();

            if (empty($data)) {
                $output->writeln("   Warning: API returned empty data for {$category}.");

                return [];
            }

            if (!array_is_list($data)) {
                $data = array_values($data);
            }

            return $data;
        } catch (\Exception $e) {
            $output->writeln("   Error fetching {$category}: ".$e->getMessage());

            return [];
        }
    }

    private function processRelics(array $items, array $dropsMap, OutputInterface $output): void
    {
        $relics = [];
        $seenRelics = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $cat = $item['category'] ?? '';
            $name = $item['name'] ?? '';

            if (false === stripos($cat, 'Relic') && false === stripos($name, 'Relic')) {
                continue;
            }

            $itemName = new WarframeItemName($name);
            $baseName = $itemName->getCleanName();

            if (isset($seenRelics[$baseName])) {
                continue;
            }
            $seenRelics[$baseName] = true;

            $rewards = [];
            if (!empty($item['rewards'])) {
                foreach ($item['rewards'] as $reward) {
                    $rewards[] = [
                        'rarity' => $reward['rarity'],
                        'chance' => $reward['chance'],
                        'item' => $reward['itemName'] ?? 'Unknown',
                    ];
                }
            } elseif (isset($dropsMap[$baseName])) {
                $rewards = $dropsMap[$baseName];
            } else {
                if (isset($dropsMap[$baseName.' Relic'])) {
                    $rewards = $dropsMap[$baseName.' Relic'];
                }
            }

            $slug = $itemName->getSlug();

            $relics[] = [
                'name' => $baseName,
                'slug' => $slug,
                'rewards' => $rewards,
            ];
        }

        usort($relics, fn ($a, $b) => strcmp($a['name'], $b['name']));

        $index = $this->meiliClient->index('relics');

        $task = $index->deleteAllDocuments();
        $this->meiliClient->waitForTask($task['taskUid']);

        $task = $index->updateSettings([
            'searchableAttributes' => ['name', 'rewards.item'],
            'filterableAttributes' => ['name'],
            'sortableAttributes' => ['name'],
        ]);
        $this->meiliClient->waitForTask($task['taskUid']);

        $task = $index->addDocuments($relics, 'slug');
        $this->meiliClient->waitForTask($task['taskUid']);

        $output->writeln(sprintf('Indexed %d relics into Meilisearch.', count($relics)));
    }

    private function processPrimes(array $items, OutputInterface $output): void
    {
        $primes = [];
        $seen = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = $item['name'] ?? '';
            if ('' === $name) {
                continue;
            }

            $name = trim($name);

            if (isset($seen[$name])) {
                continue;
            }

            if (!str_contains($name, 'Prime')) {
                continue;
            }
            if (str_contains($name, 'Set')) {
                continue;
            }

            if (empty($item['components'])) {
                continue;
            }

            $seen[$name] = true;

            $slug = strtolower(str_replace(' ', '_', $name));

            $parts = [];
            foreach ($item['components'] as $component) {
                $compName = $component['name'];
                $cleanName = str_ireplace($name.' ', '', $compName);

                $parts[] = [
                    'name' => trim($cleanName),
                    'count' => $component['itemCount'] ?? 1,
                ];
            }

            $primes[] = [
                'name' => $name,
                'slug' => $slug,
                'parts' => $parts,
            ];
        }

        usort($primes, fn ($a, $b) => strcmp($a['name'], $b['name']));

        $index = $this->meiliClient->index('primes');

        $task = $index->deleteAllDocuments();
        $this->meiliClient->waitForTask($task['taskUid']);

        $task = $index->updateSettings([
            'searchableAttributes' => ['name', 'parts.name'],
            'filterableAttributes' => ['name', 'slug'],
            'sortableAttributes' => ['name'],
        ]);
        $this->meiliClient->waitForTask($task['taskUid']);

        $task = $index->addDocuments($primes, 'slug');
        $this->meiliClient->waitForTask($task['taskUid']);

        $output->writeln(sprintf('Indexed %d primes into Meilisearch.', count($primes)));
    }
}
