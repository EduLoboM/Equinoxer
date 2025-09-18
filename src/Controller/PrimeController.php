<?php

namespace App\Controller;

use App\Service\JsonLoader;
use App\Service\WarframeLoot;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class PrimeController extends AbstractController
{
    #[Route("/primes", name: "primes")]
    public function list(JsonLoader $loader): Response
    {
        $primes = $loader->load("Primes_Normalized.json");

        return $this->render("primes/list.html.twig", [
            "primes" => $primes,
        ]);
    }

    #[Route("/primes/{slug}", name: "primes_show")]
    public function show(
        string $slug,
        JsonLoader $loader,
        WarframeLoot $search,
    ): Response {
        $primes = $loader->load("Primes_Normalized.json");
        $entry = array_values(
            array_filter($primes, fn($w) => $w["slug"] === $slug),
        );

        if (!$entry) {
            throw $this->createNotFoundException(
                "Prime '{$slug}' não encontrado",
            );
        }
        $prime = $entry[0];

        $positions = ["A" => 1, "B" => 2, "C" => 3];

        $parts = [];
        foreach ($prime["parts"] as $partData) {
            $partName = is_array($partData) ? ($partData['name'] ?? 'Unknown') : $partData;
            
            if (in_array($partName, ['Orokin Cell', 'Argon Crystal', 'Tellurium', 'Nitain Extract', 'Neural Sensors', 'Neurodes'])) {
                continue;
            }

            $fullItemName = "{$prime["name"]} {$partName}";
            $rawRelics = $loader->findRelicsByItem($fullItemName);
            $relicsWithDrops = [];

            foreach ($rawRelics as $relic) {
                $drops = $search->getMissionsForRelic($relic["name"]);
                $groups = [];

                foreach ($drops as $d) {
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
                    if (
                        !in_array(
                            $d["rotation"],
                            $groups[$key]["rotations"],
                            true,
                        )
                    ) {
                        $groups[$key]["rotations"][] = $d["rotation"];
                    }
                    $groups[$key]["chances"][] = $d["chance"] . "%";
                }

                foreach ($groups as &$g) {
                    $ps = array_map(
                        fn($c) => floatval(rtrim($c, "%")) / 100,
                        $g["chances"],
                    );
                    $prod = array_reduce(
                        $ps,
                        fn($carry, $p) => $carry * (1 - $p),
                        1.0,
                    );
                    $cycleChance = 1 - $prod;

                    $maxRot = max($g["rotations"]);
                    $missions = $positions[$maxRot] + 1;
                    $efficiency = $cycleChance / $missions;

                    $g["cycleChance"] = round($cycleChance * 100, 2) . "%";
                    $g["missionsUsed"] = $missions;
                    $g["efficiency"] = round($efficiency * 100, 2) . "%";
                }
                unset($g);

                $dropsGrouped = array_values($groups);

                $relicsWithDrops[] = array_merge($relic, [
                    "dropsGrouped" => $dropsGrouped,
                ]);
            }

            $parts[] = [
                "name" => $partName,
                "fullName" => $fullItemName,
                "relics" => $relicsWithDrops,
            ];
        }

        return $this->render("primes/show.html.twig", [
            "prime" => $prime,
            "parts" => $parts,
        ]);
    }
}
