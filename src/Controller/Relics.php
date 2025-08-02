<?php

namespace App\Controller;

use App\Service\MyJsonLoader;
use App\Service\WarframeLoot;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class Relics extends AbstractController
{
    #[Route("/relics/{slug}", name: "relic_show")]
    public function show(
        string $slug,
        MyJsonLoader $loader,
        WarframeLoot $search,
    ): Response {
        $allRelics = $loader->load("Relics_Normalized.json");
        $relic = array_filter($allRelics, fn(array $r) => $r["slug"] === $slug);
        $relic = array_shift($relic);
        $rewards = $search->getMissionsForRelic($relic["name"]);

        return $this->render("relics/show.html.twig", [
            "relic" => $relic,
            "rewards" => $rewards,
        ]);
    }
}
