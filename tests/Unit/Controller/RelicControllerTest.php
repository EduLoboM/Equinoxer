<?php

namespace App\Tests\Unit\Controller;

use App\Controller\RelicController;
use App\Service\JsonLoader;
use App\Service\WarframeLoot;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Twig\Environment;

class RelicControllerTest extends TestCase
{
    public function testShowCalculatesEfficiency(): void
    {
        $mockRelic = ['slug' => 'lith_g1', 'name' => 'Lith G1 Relic'];
        $mockMissions = [
            ['planet' => 'Void', 'mission' => 'Hepit', 'gameMode' => 'Capture', 'rotation' => 'A', 'chance' => 14.29],
        ];

        $loader = $this->createMock(JsonLoader::class);
        $loader->method('load')->willReturn([$mockRelic]);

        $search = $this->createMock(WarframeLoot::class);
        $search->method('getMissionsForRelic')->willReturn($mockMissions);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
             ->method('render')
             ->with('relics/show.html.twig', $this->callback(function ($context) {
                 return isset($context['rewards']) && null !== $context['rewards'][0]['efficiency'];
             }))
             ->willReturn('ok');

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->with('twig')->willReturn($twig);

        $controller = new RelicController();
        $controller->setContainer($container);

        $response = $controller->show('lith_g1', $loader, $search);

        $this->assertEquals('ok', $response->getContent());
    }
}
