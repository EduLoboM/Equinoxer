<?php

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
        }
        unset($g);

        usort($groups, fn ($a, $b) => floatval($b['efficiency']) <=> floatval($a['efficiency']));

        return $this->render('relics/show.html.twig', [
            'relic' => $relic,
            'rewards' => $groups,
        ]);
    }
}
