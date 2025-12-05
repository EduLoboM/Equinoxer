<?php

namespace App\Tests\Unit\Config;

use App\Config\IgnoredResources;
use PHPUnit\Framework\TestCase;

class IgnoredResourcesTest extends TestCase
{
    public function testPrimePartsConstantExists(): void
    {
        $this->assertNotEmpty(IgnoredResources::PRIME_PARTS);
    }

    public function testPrimePartsContainsExpectedResources(): void
    {
        $parts = IgnoredResources::PRIME_PARTS;

        $this->assertContains('Orokin Cell', $parts);
        $this->assertContains('Argon Crystal', $parts);
        $this->assertContains('Tellurium', $parts);
        $this->assertContains('Nitain Extract', $parts);
        $this->assertContains('Neural Sensors', $parts);
        $this->assertContains('Neurodes', $parts);
    }

    public function testPrimePartsHasExpectedCount(): void
    {
        $this->assertCount(6, IgnoredResources::PRIME_PARTS);
    }
}
