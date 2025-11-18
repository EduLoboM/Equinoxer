<?php

namespace App\Service;

use Meilisearch\Client;

class JsonLoader
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function load(string $filename): array
    {
        $indexName = $this->getIndexName($filename);
        if (!$indexName) {
            throw new \RuntimeException("Unknown file mapping for: {$filename}");
        }

        try {
            $query = (new \Meilisearch\Contracts\DocumentsQuery())->setLimit(10000);
            $result = $this->client->index($indexName)->getDocuments($query);

            if (is_iterable($result)) {
                $hits = iterator_to_array($result);
            } else {
                $hits = method_exists($result, 'getResults') ? $result->getResults() : (array) $result;
            }

            return array_map(function ($hit) {
                return (array) $hit;
            }, $hits);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function findRelicsByItem(string $itemName): array
    {
        $index = $this->client->index('relics');

        $hits = $index->search($itemName, [
            'attributesToRetrieve' => ['*'],
            'limit' => 1000,
        ])->getHits();

        return $hits;
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
