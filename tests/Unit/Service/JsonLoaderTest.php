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
            ['name' => 'Test Prime', 'slug' => 'test_prime']
        ]));

        $mockIndex = $this->createMock(Indexes::class);
        $mockIndex->method('getDocuments')->willReturn($mockResults);

        $mockClient = $this->createMock(Client::class);
        $mockClient->method('index')->willReturn($mockIndex);

        $loader = new JsonLoader($mockClient);

        $result = $loader->load('Primes_Normalized.json');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function testLoadReturnsEmptyArrayOnError(): void
    {
        $mockIndex = $this->createMock(Indexes::class);
        $mockIndex->method('getDocuments')->willThrowException(new \Exception('Index not found'));

        $mockClient = $this->createMock(Client::class);
        $mockClient->method('index')->willReturn($mockIndex);

        $loader = new JsonLoader($mockClient);

        $result = $loader->load('Primes_Normalized.json');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testLoadThrowsExceptionForUnknownFile(): void
    {
        $mockClient = $this->createMock(Client::class);
        $loader = new JsonLoader($mockClient);

        $this->expectException(\RuntimeException::class);
        $loader->load('Unknown.json');
    }
}
