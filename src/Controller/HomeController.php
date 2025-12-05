<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(): Response
    {
        $response = $this->render('home/index.html.twig');
        $response->setSharedMaxAge(3600);
        $response->setMaxAge(300);

        return $response;
    }

    #[Route('/run-update', name: 'run_update')]
    public function runUpdate(KernelInterface $kernel): Response
    {
        $application = new Application($kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'command' => 'app:update-data',
        ]);

        $output = new BufferedOutput();
        $application->run($input, $output);

        $content = $output->fetch();

        return $this->render('home/update_result.html.twig', [
            'output' => $content,
            'title' => 'DATA UPDATE RESULT',
        ]);
    }

    #[Route('/run-load', name: 'run_load')]
    public function runLoad(KernelInterface $kernel): Response
    {
        $application = new Application($kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'command' => 'app:load-data',
        ]);

        $output = new BufferedOutput();
        $application->run($input, $output);

        $content = $output->fetch();

        return $this->render('home/update_result.html.twig', [
            'output' => $content,
            'title' => 'DATA LOAD RESULT',
        ]);
    }
}
