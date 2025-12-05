<?php

declare(strict_types=1);

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
    description: 'Fetches data from Warframe API, indexes to Meilisearch, and exports to JSON',
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

    private function sanitizeSlug(string $value): string
    {
        $slug = strtolower(str_replace(' ', '_', $value));

        return preg_replace('/[^a-z0-9_-]/', '', $slug) ?? $slug;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '4G');

        try {
            $output->writeln('Fetching items from Warframe API...');

            $output->writeln('Fetching Mission Rewards...');
            $missionRewardsData = $this->fetchMissionRewards($output);
            if (null === $missionRewardsData) {
                $output->writeln('FAILED: Could not fetch mission rewards from API.');

                return Command::FAILURE;
            }

            $output->writeln('Fetching Global Drops...');
            $dropsMap = $this->fetchDrops($output);
            if (empty($dropsMap)) {
                $output->writeln('FAILED: Could not fetch drops from API.');

                return Command::FAILURE;
            }

            $output->writeln('Fetching Relics...');
            $relicItems = $this->fetchCategory('Relics', $output);
            if (empty($relicItems)) {
                $output->writeln('FAILED: Could not fetch relics from API.');

                return Command::FAILURE;
            }

            $output->writeln('Fetching Primes...');
            $primeCategories = ['Warframes', 'Primary', 'Secondary', 'Melee', 'Archwing', 'Sentinels'];
            $primeItems = [];

            foreach ($primeCategories as $cat) {
                $catItems = $this->fetchCategory($cat, $output);
                if (empty($catItems)) {
                    $output->writeln("FAILED: Could not fetch $cat from API.");

                    return Command::FAILURE;
                }
                $primeItems = array_merge($primeItems, $catItems);
            }

            $output->writeln('All API fetches successful. Processing data...');

            $output->writeln('Processing Mission Rewards...');
            $this->indexMissionRewards($missionRewardsData, $output);

            $output->writeln('Processing Relics...');
            $relics = $this->processRelics($relicItems, $dropsMap, $output);

            $output->writeln('Processing Primes...');
            $primes = $this->processPrimes($primeItems, $output);

            $output->writeln('Exporting to JSON files...');
            $this->exportToJson($relics, $primes, $missionRewardsData, $output);

            $output->writeln('Data update complete.');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('CRITICAL ERROR: '.$e->getMessage());
            $output->writeln($e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchMissionRewards(OutputInterface $output): ?array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://drops.warframestat.us/data/missionRewards.json', [
                'timeout' => 120,
            ]);

            if (200 !== $response->getStatusCode()) {
                $output->writeln('   Mission rewards fetch failed: '.$response->getStatusCode());

                return null;
            }

            $jsonData = $response->toArray();
            $output->writeln('   Fetched mission rewards successfully.');

            return $jsonData;
        } catch (\Exception $e) {
            $output->writeln('   Error fetching mission rewards: '.$e->getMessage());

            return null;
        }
    }

    /**
     * @param array<string, mixed> $jsonData
     */
    private function indexMissionRewards(array $jsonData, OutputInterface $output): void
    {
        $data = $jsonData['missionRewards'] ?? [];
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
    }

    /**
     * @return array<string, array<int, array{item: string, rarity: string, chance: float}>>
     */
    private function fetchDrops(OutputInterface $output): array
    {
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchCategory(string $category, OutputInterface $output): array
    {
        $output->writeln(" - Fetching category: {$category}");

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

    /**
     * @param array<int, array<string, mixed>>                                              $items
     * @param array<string, array<int, array{item: string, rarity: string, chance: float}>> $dropsMap
     *
     * @return array<int, array<string, mixed>>
     */
    private function processRelics(array $items, array $dropsMap, OutputInterface $output): array
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

            $slug = $this->sanitizeSlug($itemName->getSlug());

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

        $output->writeln(sprintf('   Indexed %d relics into Meilisearch.', count($relics)));

        return $relics;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     *
     * @return array<int, array<string, mixed>>
     */
    private function processPrimes(array $items, OutputInterface $output): array
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

            $slug = $this->sanitizeSlug($name);

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

        $output->writeln(sprintf('   Indexed %d primes into Meilisearch.', count($primes)));

        return $primes;
    }

    /**
     * @param array<int, array<string, mixed>> $relics
     * @param array<int, array<string, mixed>> $primes
     * @param array<string, mixed>             $missionRewardsData
     */
    private function exportToJson(array $relics, array $primes, array $missionRewardsData, OutputInterface $output): void
    {
        file_put_contents(
            $this->dataDir.'/Relics_Normalized.json',
            json_encode($relics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        $output->writeln('   Exported Relics_Normalized.json');

        file_put_contents(
            $this->dataDir.'/Primes_Normalized.json',
            json_encode($primes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        $output->writeln('   Exported Primes_Normalized.json');

        file_put_contents(
            $this->dataDir.'/missionRewards.json',
            json_encode($missionRewardsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        $output->writeln('   Exported missionRewards.json');
    }
}
