<?php

namespace App\Controller;

use App\Service\MyJsonLoader;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class Relics extends AbstractController
{
    #[Route("/relics/unvaulted", name: "relic_list")]
    public function list(MyJsonLoader $loader): Response
    {
        $allRelics = $loader->load("Relics_Normalized.json");

        $unvaulted = array_filter(
            $allRelics,
            fn(array $r) => isset($r["vaulted"]) && $r["vaulted"] === false,
        );

        return $this->render("relics/unvaulted.html.twig", [
            "relics" => $unvaulted,
        ]);
    }

    #[Route("/relics/{slug}", name: "relic_show")]
    public function show(string $slug, MyJsonLoader $loader): Response
    {
        $allRelics = $loader->load("Relics_Normalized.json");
        $relic = array_filter($allRelics, fn(array $r) => $r["slug"] === $slug);
        $relic = array_shift($relic);

        return $this->render("relics/show.html.twig", [
            "relic" => $relic,
        ]);
    }
}
