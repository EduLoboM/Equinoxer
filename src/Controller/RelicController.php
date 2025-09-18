<?php

namespace App\Controller;

use App\Service\JsonLoader;
use App\Service\WarframeLoot;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class RelicController extends AbstractController
{
    #[Route("/relics/{slug}", name: "relic_show")]
    public function show(
        string $slug,
        JsonLoader $loader,
        WarframeLoot $search,
    ): Response {
        $allRelics = $loader->load("Relics_Normalized.json");
        $relic = array_filter($allRelics, fn(array $r) => $r["slug"] === $slug);
        $relic = array_shift($relic);
        $rawRewards = $search->getMissionsForRelic($relic["name"]);
        
        $groups = [];
        $positions = ["A" => 1, "B" => 2, "C" => 3];

        foreach ($rawRewards as $d) {
            $key = "{$d["planet"]}|{$d["mission"]}|{$d["gameMode"]}";
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    "planet" => $d["planet"],
                    "mission" => $d["mission"],
                    "gameMode" => $d["gameMode"],
                    "rotations" => [],
                    "chances" => [],
                ];
            }
            if (!in_array($d["rotation"], $groups[$key]["rotations"], true)) {
                $groups[$key]["rotations"][] = $d["rotation"];
            }
            $groups[$key]["chances"][] = $d["chance"] . "%";
        }

        foreach ($groups as &$g) {
            $ps = array_map(fn($c) => floatval(rtrim($c, "%")) / 100, $g["chances"]);
            $prod = array_reduce($ps, fn($carry, $p) => $carry * (1 - $p), 1.0);
            $cycleChance = 1 - $prod;

            $maxRot = max($g["rotations"]);
            $missions = ($positions[$maxRot] ?? 3) + 1;
            $efficiency = $cycleChance / $missions;

            $g["cycleChance"] = round($cycleChance * 100, 2) . "%";
            $g["efficiency"] = round($efficiency * 100, 2) . "%";
        }
        unset($g);

        usort($groups, fn($a, $b) => floatval($b["efficiency"]) <=> floatval($a["efficiency"]));

        return $this->render("relics/show.html.twig", [
            "relic" => $relic,
            "rewards" => $groups,
        ]);
    }
}
