<?php

namespace App\Controller;

use App\Service\MyJsonLoader;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class Relics extends AbstractController
{
    #[Route("/relics", name: "relic_list")]
    public function list(MyJsonLoader $loader): Response
    {
        $allRelics = $loader->load("Relics_Normalized.json");

        $unvaulted = array_filter(
            $allRelics,
            fn(array $r) => isset($r["vaultInfo"]["vaulted"]) &&
                $r["vaultInfo"]["vaulted"] === false,
        );

        return $this->render("relics.html.twig", ["relics" => $unvaulted]);
    }

    #[Route("/warframes/{slug}", name: "relic_show")]
    public function show(string $slug, MyJsonLoader $loader): Response
    {
        $warframes = $loader->load("Warframes_Normalized.json");
        $entry = array_values(
            array_filter($warframes, function ($w) use ($slug) {
                return $w["slug"] === $slug;
            }),
        );

        if (!$entry) {
            throw $this->createNotFoundException(
                "Warframe '{$slug}' não encontrado",
            );
        }

        $warframe = $entry[0];
        $parts = [];

        foreach ($warframe["parts"] as $partName) {
            $fullItemName = $warframe["name"] . " " . $partName;

            $parts[] = [
                "name" => $partName,
                "fullName" => $fullItemName,
                "relics" => $loader->findRelicsByItem($fullItemName),
            ];
        }

        return $this->render("warframes/show.html.twig", [
            "warframe" => $warframe,
            "parts" => $parts,
            "loader" => $loader,
        ]);
    }
}
