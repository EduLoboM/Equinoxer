<?php

namespace App\Tests\Unit\Service;

use App\Service\WarframeLoot;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;
use PHPUnit\Framework\TestCase;

class WarframeLootTest extends TestCase
{
    public function testGetMissionsForRelicReturnsLocations(): void
    {
        $mockDocument = [
            'id' => 'lith_g1',
            'name' => 'Lith G1 Relic',
            'locations' => [
                [
                    'planet' => 'Void',
                    'mission' => 'Hepit',
                    'rotation' => 'A',
                    'chance' => 14.29,
                    'gameMode' => 'Capture',
                ],
            ],
        ];

        $mockIndex = $this->createMock(Indexes::class);
        $mockIndex->method('getDocument')->willReturn($mockDocument);

        $mockClient = $this->createMock(Client::class);
        $mockClient->method('index')->willReturn($mockIndex);

        $mockLogger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $service = new WarframeLoot($mockClient, $mockLogger);

        $results = $service->getMissionsForRelic('Lith G1');

        $this->assertCount(1, $results);
        $this->assertEquals('Void', $results[0]['planet']);
        $this->assertEquals('Hepit', $results[0]['mission']);
        $this->assertEquals('Capture', $results[0]['gameMode']);
        $this->assertEquals(14.29, $results[0]['chance']);
    }

    public function testGetMissionsForRelicReturnsEmptyOnNotFound(): void
    {
        $mockIndex = $this->createMock(Indexes::class);
        $mockIndex->method('getDocument')->willThrowException(new \Exception('Document not found'));

        $mockClient = $this->createMock(Client::class);
        $mockClient->method('index')->willReturn($mockIndex);

        $mockLogger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $service = new WarframeLoot($mockClient, $mockLogger);

        $results = $service->getMissionsForRelic('Nonexistent Z9');

        $this->assertSame([], $results);
        $this->assertEmpty($results);
    }
}
