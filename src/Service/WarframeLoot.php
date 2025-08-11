<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class WarframeLoot
{
    private HttpClientInterface $httpClient;
    private CacheInterface $cache;

    public function __construct(
        HttpClientInterface $httpClient,
        CacheInterface $cache,
    ) {
        $this->httpClient = $httpClient;
        $this->cache = $cache;
    }

    public function getMissionsForRelic(string $relicName): array
    {
        $lootTable = $this->getCachedLootTable();

        $candidates = [
            $relicName,
            $relicName . " Relic",
            preg_replace('/ (Intact|Exceptional|Flawless|Radiant)$/', '', $relicName) . " Relic"
        ];

        foreach ($candidates as $candidate) {
            if (!empty($lootTable[$candidate])) {
                 $result = $lootTable[$candidate];
                 usort($result, fn($a, $b) => $b["chance"] <=> $a["chance"]);
                 return $result;
            }
        }

        return [];
    }

    private function getCachedLootTable(): array
    {
        return $this->cache->get(
            "warframe_mission_rewards_v2",
            function (ItemInterface $item) {
                $item->expiresAfter(3600 * 24);
                return $this->fetchAndBuildIndex();
            },
        );
    }

    private function fetchAndBuildIndex(): array
    {
        $response = $this->httpClient->request(
            "GET",
            "https://drops.warframestat.us/data/missionRewards.json",
        );

        if ($response->getStatusCode() !== 200) {
            return [];
        }

        $jsonData = $response->toArray();
        $data = $jsonData["missionRewards"] ?? [];
        $index = [];

        foreach ($data as $planet => $missions) {
            if (!is_array($missions)) {
                continue;
            }
            foreach ($missions as $mission => $missionData) {
                $rewards = $missionData["rewards"] ?? [];
                if (!is_array($rewards)) {
                    continue;
                }
                foreach ($rewards as $rotation => $items) {
                    if (!is_array($items)) {
                        continue;
                    }
                    foreach ($items as $item) {
                        $itemName = $item["itemName"] ?? "";
                        if (!$itemName) {
                            continue;
                        }

                        if (!isset($index[$itemName])) {
                            $index[$itemName] = [];
                        }

                        $index[$itemName][] = [
                            "planet" => $planet,
                            "mission" => $mission,
                            "rotation" => $rotation,
                            "chance" => $item["chance"] ?? 0,
                            "gameMode" =>
                                $missionData["gameMode"] ?? "Unknown",
                        ];
                    }
                }
            }
        }

        return $index;
    }
}
