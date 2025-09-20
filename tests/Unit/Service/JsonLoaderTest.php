<?php

namespace App\Tests\Unit\Service;

use App\Service\JsonLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;

class JsonLoaderTest extends TestCase
{
    public function testLoadReturnsArray(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(['some' => 'data']);

        $loader = new JsonLoader('/tmp', $cache);
        
        $result = $loader->load('test.json');
        
        $this->assertIsArray($result);
        $this->assertEquals(['some' => 'data'], $result);
    }
}
