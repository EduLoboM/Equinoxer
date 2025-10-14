<?php

namespace App\Tests\Unit\Service;

use App\Service\WarframeLoot;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class WarframeLootTest extends TestCase
{
    public function testGetMissionsForRelicParsesJsonCorrectly(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('toArray')->willReturn([
            'missionRewards' => [
                'Void' => [
                    'Hepit' => [
                        'gameMode' => 'Capture',
                        'rewards' => [
                            'A' => [
                                ['itemName' => 'Lith G1 Relic', 'chance' => 14.29],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($mockResponse);

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createMock(ItemInterface::class);

                return $callback($item);
            });

        $service = new WarframeLoot($httpClient, $cache);

        $results = $service->getMissionsForRelic('Lith G1');

        $this->assertCount(1, $results);
        $this->assertEquals('Void', $results[0]['planet']);
        $this->assertEquals('Hepit', $results[0]['mission']);
        $this->assertEquals('Capture', $results[0]['gameMode']);
        $this->assertEquals(14.29, $results[0]['chance']);
    }
}
