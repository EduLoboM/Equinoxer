<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class WarframeLoot
{
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function getMissionsForRelic(string $relicName): array
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
        $result = [];

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
                        if (($item["itemName"] ?? "") === $relicName) {
                            $result[] = [
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
        }

        usort($result, fn($a, $b) => $b["chance"] <=> $a["chance"]);
        return $result;
    }
}
