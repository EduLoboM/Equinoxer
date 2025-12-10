<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\JsonLoader;
use App\Service\PrimeService;
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
        PrimeService $primeService,
    ): Response {
        $data = $primeService->getPrimeDetails($slug);

        if (!$data) {
            throw $this->createNotFoundException("Prime '{$slug}' não encontrado");
        }

        $response = $this->render('primes/show.html.twig', $data);
        $response->setSharedMaxAge(3600);
        $response->setMaxAge(300);

        return $response;
    }
}
