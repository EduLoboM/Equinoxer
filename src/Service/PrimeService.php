<?php

declare(strict_types=1);

namespace App\Service;

use App\Config\IgnoredResources;

class PrimeService
{
    public function __construct(
        private JsonLoader $loader,
        private WarframeLoot $search,
        private DropEfficiencyCalculator $calculator,
    ) {
    }

    public function getPrimeDetails(string $slug): ?array
    {
        $primes = $this->loader->load('Primes_Normalized.json');
        $entry = array_values(
            array_filter($primes, fn ($w) => $w['slug'] === $slug),
        );

        if (!$entry) {
            return null;
        }
        $prime = $entry[0];

        $parts = [];
        foreach ($prime['parts'] as $partData) {
            $partName = is_array($partData) ? ($partData['name'] ?? 'Unknown') : $partData;

            if (in_array($partName, IgnoredResources::PRIME_PARTS, true)) {
                continue;
            }

            $fullItemName = "{$prime['name']} {$partName}";
            $rawRelics = $this->loader->findRelicsByItem($fullItemName);
            $relicsWithDrops = [];

            foreach ($rawRelics as $relic) {
                $drops = $this->search->getMissionsForRelic($relic['name']);
                $groups = [];

                foreach ($drops as $d) {
                    $key = "{$d['planet']}|{$d['mission']}|{$d['gameMode']}";
                    if (!isset($groups[$key])) {
                        $groups[$key] = [
                            'planet' => $d['planet'],
                            'mission' => $d['mission'],
                            'gameMode' => $d['gameMode'],
                            'rotations' => [],
                            'chances' => [],
                            'cycleChance' => '',
                            'missionsUsed' => 0,
                            'efficiency' => '',
                            'rotationPattern' => '',
                        ];
                    }
                    if (
                        !in_array(
                            $d['rotation'],
                            $groups[$key]['rotations'],
                            true,
                        )
                    ) {
                        $groups[$key]['rotations'][] = $d['rotation'];
                    }
                    $groups[$key]['chances'][] = $d['chance'].'%';
                }

                foreach ($groups as &$g) {
                    $maxRot = max($g['rotations']);
                    $result = $this->calculator->calculateFromChanceStrings($g['chances'], $maxRot);

                    $g['cycleChance'] = $result->getCycleChanceFormatted();
                    $g['missionsUsed'] = $result->missionsUsed;
                    $g['efficiency'] = $result->getEfficiencyFormatted();
                    $g['rotationPattern'] = $this->computeRotationPattern($maxRot);
                }
                unset($g);

                $dropsGrouped = array_values($groups);

                $best = null;
                $bestEff = -1.0;
                foreach ($dropsGrouped as $drop) {
                    $eff = (float) str_replace('%', '', (string) $drop['efficiency']);
                    if ($eff > $bestEff) {
                        $bestEff = $eff;
                        $best = $drop;
                    }
                }

                $slugItem = strtolower(str_replace([' Relic', ' '], ['', '_'], $relic['name']));

                $relicsWithDrops[] = array_merge($relic, [
                    'dropsGrouped' => $dropsGrouped,
                    'bestMission' => $best,
                    'slug' => $slugItem,
                ]);
            }

            $parts[] = [
                'name' => $partName,
                'fullName' => $fullItemName,
                'relics' => $relicsWithDrops,
            ];
        }

        return [
            'prime' => $prime,
            'parts' => $parts,
        ];
    }

    private function computeRotationPattern(string $maxRotation): string
    {
        return match ($maxRotation) {
            'A' => 'AA',
            'B' => 'AAB',
            default => 'AABC',
        };
    }
}
