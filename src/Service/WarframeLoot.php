<?php

declare(strict_types=1);

namespace App\Service;

class WarframeLoot
{
    private \Meilisearch\Client $client;

    public function __construct(\Meilisearch\Client $client)
    {
        $this->client = $client;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMissionsForRelic(string $relicName): array
    {
        $candidates = [
            $relicName,
            $relicName.' Relic',
            preg_replace('/ (Intact|Exceptional|Flawless|Radiant)$/', '', $relicName).' Relic',
        ];

        $index = $this->client->index('mission_rewards');

        foreach ($candidates as $candidate) {
            try {
                $slug = (new \App\ValueObject\WarframeItemName($candidate))->getSlug();
                $slug = preg_replace('/[^a-zA-Z0-9_-]/', '_', $slug);

                $document = $index->getDocument($slug);

                if ($document) {
                    $data = (array) $document;

                    return $data['locations'] ?? [];
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return [];
    }
}
