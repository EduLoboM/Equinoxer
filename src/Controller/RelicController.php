<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\DropEfficiencyCalculator;
use App\Service\JsonLoader;
use App\Service\WarframeLoot;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RelicController extends AbstractController
{
    #[Route('/relics/{slug}', name: 'relic_show')]
    public function show(
        string $slug,
        JsonLoader $loader,
        WarframeLoot $search,
        DropEfficiencyCalculator $calculator,
    ): Response {
        $allRelics = $loader->load('Relics_Normalized.json');
        $relic = array_filter($allRelics, fn (array $r) => $r['slug'] === $slug);
        $relic = array_shift($relic);
        $rawRewards = $search->getMissionsForRelic($relic['name']);

        $groups = [];

        foreach ($rawRewards as $d) {
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
            if (!in_array($d['rotation'], $groups[$key]['rotations'], true)) {
                $groups[$key]['rotations'][] = $d['rotation'];
            }
            $groups[$key]['chances'][] = $d['chance'].'%';
        }

        foreach ($groups as &$g) {
            $maxRot = max($g['rotations']);
            $result = $calculator->calculateFromChanceStrings($g['chances'], $maxRot);

            $g['cycleChance'] = $result->getCycleChanceFormatted();
            $g['efficiency'] = $result->getEfficiencyFormatted();
            $g['rotationPattern'] = $this->computeRotationPattern($maxRot);
        }
        unset($g);

        usort($groups, function ($a, $b) {
            $effA = isset($a['efficiency']) ? floatval($a['efficiency']) : 0.0;
            $effB = isset($b['efficiency']) ? floatval($b['efficiency']) : 0.0;

            return $effB <=> $effA;
        });

        $response = $this->render('relics/show.html.twig', [
            'relic' => $relic,
            'rewards' => $groups,
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
