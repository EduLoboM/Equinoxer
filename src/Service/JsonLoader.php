<?php

declare(strict_types=1);

namespace App\Service;

use Meilisearch\Client;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class JsonLoader
{
    private Client $client;
    private \Psr\Log\LoggerInterface $logger;
    private string $dataDir;

    public function __construct(
        Client $client,
        \Psr\Log\LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%/data')] string $dataDir,
    ) {
        $this->client = $client;
        $this->logger = $logger;
        $this->dataDir = $dataDir;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function load(string $filename): array
    {
        $indexName = $this->getIndexName($filename);
        if (!$indexName) {
            throw new \RuntimeException("Unknown file mapping for: {$filename}");
        }

        try {
            $query = (new \Meilisearch\Contracts\DocumentsQuery())->setLimit(10000);
            $result = $this->client->index($indexName)->getDocuments($query);
            $hits = iterator_to_array($result);

            if (empty($hits)) {
                throw new \Exception('No hits found in Meilisearch, falling back to file.');
            }

            return array_map(function ($hit) {
                return (array) $hit;
            }, $hits);
        } catch (\Throwable $e) {
            $this->logger->warning('Meilisearch load failed, falling back to file', [
                'index' => $indexName,
                'error' => $e->getMessage(),
            ]);

            return $this->loadFromFile($filename);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadFromFile(string $filename): array
    {
        $path = $this->dataDir.'/'.$filename;
        if (!file_exists($path)) {
            $this->logger->error('File not found for fallback', ['path' => $path]);

            return [];
        }

        $content = file_get_contents($path);
        if (!$content) {
            return [];
        }

        try {
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->error('JSON decode error', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findRelicsByItem(string $itemName): array
    {
        try {
            $index = $this->client->index('relics');

            $hits = $index->search($itemName, [
                'attributesToRetrieve' => ['*'],
                'limit' => 1000,
            ])->getHits();

            return $hits;
        } catch (\Meilisearch\Exceptions\ApiException $e) {
            // Index might not exist yet if no data loaded
            if (isset($e->httpStatus) && 404 === $e->httpStatus) {
                return [];
            }
            $this->logger->warning('Error searching relics index', ['error' => $e->getMessage()]);

            return [];
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error searching relics', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function getIndexName(string $filename): ?string
    {
        return match ($filename) {
            'Primes_Normalized.json' => 'primes',
            'Relics_Normalized.json' => 'relics',
            default => null,
        };
    }
}
