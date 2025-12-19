<?php

namespace App\Tests\Unit\Controller;

use App\Controller\PrimeController;
use App\Service\DropEfficiencyCalculator;
use App\Service\JsonLoader;
use App\Service\WarframeLoot;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

class PrimeControllerTest extends TestCase
{
    public function testList(): void
    {
        $loader = $this->createMock(JsonLoader::class);
        $loader->method('load')->willReturn([]);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())->method('render')->willReturn('ok');

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->with('twig')->willReturn($twig);

        $controller = new PrimeController();
        $controller->setContainer($container);

        $response = $controller->list($loader);
        $this->assertEquals('ok', $response->getContent());
    }

    public function testShowNotFound(): void
    {
        $primeService = $this->createMock(\App\Service\PrimeService::class);
        $primeService->method('getPrimeDetails')->willReturn(null);

        $controller = new PrimeController();
        $container = $this->createMock(ContainerInterface::class);
        $controller->setContainer($container);

        $this->expectException(NotFoundHttpException::class);
        $controller->show('slug', $primeService);
    }
}
