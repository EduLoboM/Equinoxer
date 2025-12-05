<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\JsonLoader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RelicListController extends AbstractController
{
    #[Route('/relics', name: 'relics_list')]
    public function index(JsonLoader $loader): Response
    {
        $relics = $loader->load('Relics_Normalized.json');

        $response = $this->render('relics/list.html.twig', [
            'relics' => $relics,
        ]);
        $response->setSharedMaxAge(3600);
        $response->setMaxAge(300);

        return $response;
    }
}
