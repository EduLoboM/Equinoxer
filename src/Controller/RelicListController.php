<?php

namespace App\Controller;

use App\Service\MyJsonLoader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RelicListController extends AbstractController
{
    #[Route('/relics', name: 'relics_list')]
    public function index(MyJsonLoader $loader): Response
    {
        $relics = $loader->load('Relics_Normalized.json');

        return $this->render('relics/list.html.twig', [
            'relics' => $relics,
        ]);
    }
}
