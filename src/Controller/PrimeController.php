<?php

declare(strict_types=1);

namespace App\Controller;

use App\Config\IgnoredResources;
use App\Service\DropEfficiencyCalculator;
use App\Service\JsonLoader;
use App\Service\WarframeLoot;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PrimeController extends AbstractController
{
    #[Route('/primes', name: 'primes')]
    public function list(JsonLoader $loader): Response
    {
        $primes = $loader->load('Primes_Normalized.json');

        $response = $this->render('primes/list.html.twig', [
            'primes' => $primes,
        ]);
        $response->setSharedMaxAge(3600);
        $response->setMaxAge(300);

        return $response;
    }

    #[Route('/primes/{slug}', name: 'primes_show')]
    public function show(
        string $slug,
        JsonLoader $loader,
        WarframeLoot $search,
        DropEfficiencyCalculator $calculator,
    ): Response {
        $primes = $loader->load('Primes_Normalized.json');
        $entry = array_values(
            array_filter($primes, fn ($w) => $w['slug'] === $slug),
        );

        if (!$entry) {
            throw $this->createNotFoundException("Prime '{$slug}' não encontrado");
        }
        $prime = $entry[0];

        $parts = [];
        foreach ($prime['parts'] as $partData) {
            $partName = is_array($partData) ? ($partData['name'] ?? 'Unknown') : $partData;

            if (in_array($partName, IgnoredResources::PRIME_PARTS, true)) {
                continue;
            }

            $fullItemName = "{$prime['name']} {$partName}";
            $rawRelics = $loader->findRelicsByItem($fullItemName);
            $relicsWithDrops = [];

            foreach ($rawRelics as $relic) {
                $drops = $search->getMissionsForRelic($relic['name']);
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
                    $result = $calculator->calculateFromChanceStrings($g['chances'], $maxRot);

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
                    if (isset($drop['efficiency'])) {
                        $eff = (float) str_replace('%', '', (string) $drop['efficiency']);
                        if ($eff > $bestEff) {
                            $bestEff = $eff;
                            $best = $drop;
                        }
                    }
                }

                $slug = strtolower(str_replace([' Relic', ' '], ['', '_'], $relic['name']));

                $relicsWithDrops[] = array_merge($relic, [
                    'dropsGrouped' => $dropsGrouped,
                    'bestMission' => $best,
                    'slug' => $slug,
                ]);
            }

            $parts[] = [
                'name' => $partName,
                'fullName' => $fullItemName,
                'relics' => $relicsWithDrops,
            ];
        }

        $response = $this->render('primes/show.html.twig', [
            'prime' => $prime,
            'parts' => $parts,
        ]);
        $response->setSharedMaxAge(3600);
        $response->setMaxAge(300);

        return $response;
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
