<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class JsonLoader
{
    private string $projectDir;
    private CacheInterface $cache;
    private array $memoized = [];

    public function __construct(string $projectDir, CacheInterface $cache)
    {
        $this->projectDir = $projectDir;
        $this->cache = $cache;
    }

    public function load(string $filename): array
    {
        if (isset($this->memoized[$filename])) {
            return $this->memoized[$filename];
        }

        $this->memoized[$filename] = $this->cache->get(
            'json_data_' . md5($filename),
            function (ItemInterface $item) use ($filename) {
                $item->expiresAfter(3600);
                
                $path = $this->projectDir . "/data/" . $filename;
                if (!file_exists($path)) {
                    throw new \RuntimeException(
                        "Arquivo JSON não encontrado em {$path}",
                    );
                }
                $json = file_get_contents($path);
                return json_decode($json, true);
            }
        );

        return $this->memoized[$filename];
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
                if (isset($reward["item"]) && str_contains($reward["item"], $itemName)) {
                    return true;
                }
            }
            return false;
        });
    }
}
