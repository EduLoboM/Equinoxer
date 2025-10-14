<?php

namespace App\Tests\Unit\Controller;

use App\Controller\HomeController;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class HomeControllerTest extends TestCase
{
    public function testIndexRendersTemplate(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('home/index.html.twig')
            ->willReturn('content');

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->with('twig')->willReturn($twig);

        $controller = new HomeController();
        $controller->setContainer($container);

        $response = $controller->index();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('content', $response->getContent());
    }
}
