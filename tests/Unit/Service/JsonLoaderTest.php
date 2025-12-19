<?php

namespace App\Tests\Unit\Service;

use App\Service\JsonLoader;
use Meilisearch\Client;
use Meilisearch\Contracts\DocumentsResults;
use Meilisearch\Endpoints\Indexes;
use PHPUnit\Framework\TestCase;

class JsonLoaderTest extends TestCase
{
    public function testLoadReturnsArray(): void
    {
        $mockResults = $this->createMock(DocumentsResults::class);
        $mockResults->method('getIterator')->willReturn(new \ArrayIterator([
            ['name' => 'Test Prime', 'slug' => 'test_prime'],
        ]));

        $mockIndex = $this->createMock(Indexes::class);
        $mockIndex->method('getDocuments')->willReturn($mockResults);

        $mockClient = $this->createMock(Client::class);
        $mockClient->method('index')->willReturn($mockIndex);

        $mockLogger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $loader = new JsonLoader($mockClient, $mockLogger, '/tmp');

        $result = $loader->load('Primes_Normalized.json');

        $this->assertNotEmpty($result);
        $this->assertCount(1, $result);
    }

    public function testLoadReturnsEmptyArrayOnError(): void
    {
        $mockIndex = $this->createMock(Indexes::class);
        $mockIndex->method('getDocuments')->willThrowException(new \Exception('Index not found'));

        $mockClient = $this->createMock(Client::class);
        $mockClient->method('index')->willReturn($mockIndex);

        $mockLogger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $loader = new JsonLoader($mockClient, $mockLogger, '/tmp');

        $result = $loader->load('Primes_Normalized.json');

        $this->assertSame([], $result);
        $this->assertEmpty($result);
    }

    public function testLoadThrowsExceptionForUnknownFile(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockLogger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $loader = new JsonLoader($mockClient, $mockLogger, '/tmp');

        $this->expectException(\RuntimeException::class);
        $loader->load('Unknown.json');
    }
}
