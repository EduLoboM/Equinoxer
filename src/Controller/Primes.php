<?php

namespace App\Controller;

use App\Service\MyJsonLoader;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class Primes extends AbstractController
{
    #[Route("/primes", name: "primes")]
    public function list(MyJsonLoader $loader)
    {
        $primes = $loader->load("Primes_Normalized.json");

        return $this->render("primes/list.html.twig", [
            "primes" => $primes,
        ]);
    }

    #[Route("/primes/{slug}", name: "primes_show")]
    public function show(string $slug, MyJsonLoader $loader): Response
    {
        $primes = $loader->load("Primes_Normalized.json");
        $entry = array_values(
            array_filter($primes, function ($w) use ($slug) {
                return $w["slug"] === $slug;
            }),
        );

        if (!$entry) {
            throw $this->createNotFoundException(
                "Warframe '{$slug}' não encontrado",
            );
        }

        $prime = $entry[0];
        $parts = [];

        foreach ($prime["parts"] as $partName) {
            $fullItemName = $prime["name"] . " " . $partName;

            $parts[] = [
                "name" => $partName,
                "fullName" => $fullItemName,
                "relics" => $loader->findRelicsByItem($fullItemName),
            ];
        }

        return $this->render("primes/show.html.twig", [
            "prime" => $prime,
            "parts" => $parts,
            "loader" => $loader,
        ]);
    }
}
