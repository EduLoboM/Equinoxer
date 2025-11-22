<?php

declare(strict_types=1);

namespace App\Command;

use App\ValueObject\WarframeItemName;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:load-data',
    description: 'Loads data from local JSON files into Meilisearch',
)]
class LoadDataCommand extends Command
{
    private \Meilisearch\Client $meiliClient;
    private string $dataDir;

    public function __construct(
        \Meilisearch\Client $meiliClient,
        #[Autowire('%kernel.project_dir%/data')] string $dataDir,
    ) {
        $this->meiliClient = $meiliClient;
        $this->dataDir = $dataDir;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '4G');

        try {
            $output->writeln('Loading data from local JSON files into Meilisearch...');

            $this->loadRelics($output);
            $this->loadPrimes($output);
            $this->loadMissionRewards($output);

            $output->writeln('Data load complete.');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('CRITICAL ERROR: '.$e->getMessage());
            $output->writeln($e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    private function loadRelics(OutputInterface $output): void
    {
        $path = $this->dataDir.'/Relics_Normalized.json';
        if (!file_exists($path)) {
            $output->writeln('Error: Relics_Normalized.json not found.');

            return;
        }

        $data = json_decode(file_get_contents($path), true);
        if (!$data) {
            $output->writeln('Error: Failed to decode Relics_Normalized.json.');

            return;
        }

        foreach ($data as &$item) {
            if (isset($item['slug'])) {
                $item['slug'] = preg_replace('/[^a-zA-Z0-9_-]/', '_', $item['slug']);
            }
        }
        unset($item);

        $index = $this->meiliClient->index('relics');

        $task = $index->deleteAllDocuments();
        $this->meiliClient->waitForTask($task['taskUid']);

        $task = $index->updateSettings([
            'searchableAttributes' => ['name', 'rewards.item'],
            'filterableAttributes' => ['name'],
            'sortableAttributes' => ['name'],
        ]);
        $this->meiliClient->waitForTask($task['taskUid']);

        $task = $index->addDocuments($data, 'slug');
        $this->meiliClient->waitForTask($task['taskUid']);

        $output->writeln(sprintf('Indexed %d relics from JSON.', count($data)));
    }

    private function loadPrimes(OutputInterface $output): void
    {
        $path = $this->dataDir.'/Primes_Normalized.json';
        if (!file_exists($path)) {
            $output->writeln('Error: Primes_Normalized.json not found.');

            return;
        }

        $data = json_decode(file_get_contents($path), true);
        if (!$data) {
            $output->writeln('Error: Failed to decode Primes_Normalized.json.');

            return;
        }

        foreach ($data as &$item) {
            if (isset($item['slug'])) {
                $item['slug'] = preg_replace('/[^a-zA-Z0-9_-]/', '_', $item['slug']);
            }
        }
        unset($item);

        $index = $this->meiliClient->index('primes');

        $task = $index->deleteAllDocuments();
        $this->meiliClient->waitForTask($task['taskUid']);

        $task = $index->updateSettings([
            'searchableAttributes' => ['name', 'parts.name'],
            'filterableAttributes' => ['name', 'slug'],
            'sortableAttributes' => ['name'],
        ]);
        $this->meiliClient->waitForTask($task['taskUid']);

        $task = $index->addDocuments($data, 'slug');
        $this->meiliClient->waitForTask($task['taskUid']);

        $output->writeln(sprintf('Indexed %d primes from JSON.', count($data)));
    }

    private function loadMissionRewards(OutputInterface $output): void
    {
        $path = $this->dataDir.'/missionRewards.json';
        if (!file_exists($path)) {
            $output->writeln('Warning: missionRewards.json not found. Skipping.');

            return;
        }

        $jsonData = json_decode(file_get_contents($path), true);
        if (!$jsonData) {
            $output->writeln('Error: Failed to decode missionRewards.json.');

            return;
        }

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

        $output->writeln(sprintf('Indexed mission rewards for %d items from JSON.', count($documents)));
    }
}
