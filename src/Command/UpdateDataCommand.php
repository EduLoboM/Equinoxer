<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:update-data',
    description: 'Updates local JSON data from Warframe API',
)]
class UpdateDataCommand extends Command
{
    private HttpClientInterface $httpClient;
    private string $dataDir;

    public function __construct(
        HttpClientInterface $httpClient,
        #[Autowire('%kernel.project_dir%/data')] string $dataDir
    ) {
        $this->httpClient = $httpClient;
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

            $output->writeln('Processing Relics...');
            $relicItems = $this->fetchCategory('Relics', $output);
            $this->processRelics($relicItems, $dropsMap, $output);

            $output->writeln('Processing Primes...');
            $primeCategories = ['Warframes', 'Primary', 'Secondary', 'Melee', 'Archwing', 'Sentinels', 'Pets', 'Skins'];
            
            $primeItems = [];
            
            foreach ($primeCategories as $cat) {
                 gc_collect_cycles();
                 $catItems = $this->fetchCategory($cat, $output);
                 $primeItems = array_merge($primeItems, $catItems);
            }
            
            $this->processPrimes($primeItems, $output);

            $output->writeln('Data update complete.');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('CRITICAL ERROR: ' . $e->getMessage());
            $output->writeln($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    private function fetchDrops(OutputInterface $output): array
    {
        try {
            $response = $this->httpClient->request('GET', "https://api.warframestat.us/drops", [
                'timeout' => 120,
            ]);
            
            if ($response->getStatusCode() !== 200) {
                 $output->writeln("   Drops fetch failed: " . $response->getStatusCode());
                 return [];
            }
            
            $data = $response->toArray();
            $map = [];
            
            foreach ($data as $drop) {
                $place = $drop['place'] ?? '';
                if (!str_contains($place, 'Relic')) {
                    continue;
                }
                
                $basePlace = preg_replace('/\s+\(?(Intact|Exceptional|Flawless|Radiant)\)?$/i', '', $place);
                $basePlace = trim($basePlace);
                
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
            
            $output->writeln("   Fetched and processed " . count($map) . " relic drop tables.");
            return $map;
        } catch (\Exception $e) {
            $output->writeln("   Error fetching drops: " . $e->getMessage());
            return [];
        }
    }

    private function fetchCategory(string $category, OutputInterface $output): array
    {
        $output->writeln(" - Fetching category: {$category} (query param)");
        
        try {
            $response = $this->httpClient->request('GET', "https://api.warframestat.us/items", [
                'query' => [
                    'category' => $category,
                ],
                'timeout' => 60,
            ]);
            
            if ($response->getStatusCode() !== 200) {
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
            $output->writeln("   Error fetching {$category}: " . $e->getMessage());
            return [];
        }
    }

    private function processRelics(array $items, array $dropsMap, OutputInterface $output): void
    {
        $relics = [];
        $seenRelics = [];
        
        foreach ($items as $item) {
            if (!is_array($item)) { continue; }
            
            $cat = $item['category'] ?? '';
            $name = $item['name'] ?? '';

            if (stripos($cat, 'Relic') === false && stripos($name, 'Relic') === false) {
                 continue;
            }

            $baseName = preg_replace('/\s+\(?(Intact|Exceptional|Flawless|Radiant)\)?$/i', '', $name);
            $baseName = trim($baseName);

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
                if (isset($dropsMap[$baseName . " Relic"])) {
                    $rewards = $dropsMap[$baseName . " Relic"];
                }
            }
            
            $slug = strtolower(str_replace(' ', '_', $baseName));
            $parts = explode(' ', $baseName);
            if (count($parts) >= 2) {
                 $tier = strtolower($parts[0]);
                 $code = strtolower($parts[1]);
                 if ($tier !== 'unknown') {
                      $slug = "{$tier}_{$code}";
                      if ($tier === 'requiem') $slug = "requiem_{$code}";
                 }
            }

            $relics[] = [
                'name' => $baseName,
                'slug' => $slug,
                'rewards' => $rewards,
            ];
        }

        usort($relics, fn($a, $b) => strcmp($a['name'], $b['name']));

        file_put_contents(
            $this->dataDir . '/Relics_Normalized.json',
            json_encode($relics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        $output->writeln(sprintf('Saved %d relics.', count($relics)));
    }

    private function processPrimes(array $items, OutputInterface $output): void
    {
        $primes = [];
        $seen = [];
        
        foreach ($items as $item) {
            if (!is_array($item)) { continue; }
            
            $name = $item['name'] ?? '';
            if ($name === '') { continue; }
            
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
                 $cleanName = str_ireplace($name . ' ', '', $compName);
                 
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

        usort($primes, fn($a, $b) => strcmp($a['name'], $b['name']));

        file_put_contents(
            $this->dataDir . '/Primes_Normalized.json',
            json_encode($primes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        $output->writeln(sprintf('Saved %d primes.', count($primes)));
    }
}
