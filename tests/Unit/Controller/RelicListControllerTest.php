<?php

namespace App\Tests\Unit\Controller;

use App\Controller\RelicListController;
use App\Service\JsonLoader;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class RelicListControllerTest extends TestCase
{
    public function testIndexLoadsRelicsAndRenders(): void
    {
        $loader = $this->createMock(JsonLoader::class);
        $loader->expects($this->once())
            ->method('load')
            ->with('Relics_Normalized.json')
            ->willReturn(['relic1', 'relic2']);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('relics/list.html.twig', ['relics' => ['relic1', 'relic2']])
            ->willReturn('html content');

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->with('twig')->willReturn($twig);

        $controller = new RelicListController();
        $controller->setContainer($container);

        $response = $controller->index($loader);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('html content', $response->getContent());
    }
}
