<?php

declare(strict_types=1);

namespace App\Service;

class WarframeLoot
{
    private \Meilisearch\Client $client;
    private \Psr\Log\LoggerInterface $logger;

    public function __construct(
        \Meilisearch\Client $client,
        \Psr\Log\LoggerInterface $logger,
    ) {
        $this->client = $client;
        $this->logger = $logger;
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
            } catch (\Meilisearch\Exceptions\ApiException $e) {
                if (isset($e->httpStatus) && $e->httpStatus === 404) {
                    // Document not found, try next candidate
                    continue;
                }
                $this->logger->error('Meilisearch error fetching relic missions', [
                    'relic' => $relicName,
                    'candidate' => $candidate,
                    'error' => $e->getMessage(),
                ]);
            } catch (\Throwable $e) {
                $this->logger->critical('Unexpected error fetching relic missions', [
                    'relic' => $relicName,
                    'candidate' => $candidate,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [];
    }
}
