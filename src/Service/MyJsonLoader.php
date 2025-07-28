<?php

namespace App\Service;

class MyJsonLoader
{
    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    public function load(string $filename): array
    {
        $path = $this->projectDir . "/data/" . $filename;
        if (!file_exists($path)) {
            throw new \RuntimeException(
                "Arquivo JSON não encontrado em {$path}",
            );
        }
        $json = file_get_contents($path);
        return json_decode($json, true);
    }

    public function findRelicsByItem(string $itemName): array
    {
        $allRelics = $this->load("Relics_Normalized.json");

        if (!is_array($allRelics)) {
            return [];
        }

        return array_filter($allRelics, function ($r) use ($itemName) {
            if (!isset($r["rewards"]) || !is_array($r["rewards"])) {
                return false;
            }

            foreach ($r["rewards"] as $reward) {
                if (
                    isset($reward["item"]["name"]) &&
                    $reward["item"]["name"] === $itemName
                ) {
                    return true;
                }
            }
            return false;
        });
    }

    public function isVaulted(string $relicName): bool
    {
        $allRelics = $this->load("Relics_Normalized.json");

        if (!is_array($allRelics)) {
            return false;
        }

        foreach ($allRelics as $relic) {
            if (isset($relic["name"]) && $relic["name"] === $relicName) {
                if (isset($relic["vaultInfo"]["vaulted"])) {
                    return (bool) $relic["vaultInfo"]["vaulted"];
                }
                return false;
            }
        }
        return false;
    }
}
