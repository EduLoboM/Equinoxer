<?php

namespace App\Tests\Unit\DTO;

use App\DTO\DropEfficiencyResult;
use PHPUnit\Framework\TestCase;

class DropEfficiencyResultTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $result = new DropEfficiencyResult(0.25, 4, 0.75);

        $this->assertEquals(0.25, $result->cycleChance);
        $this->assertEquals(4, $result->missionsUsed);
        $this->assertEquals(0.75, $result->efficiency);
    }

    public function testGetCycleChanceFormatted(): void
    {
        $result = new DropEfficiencyResult(0.1234, 1, 0.5);

        $this->assertEquals('12.34%', $result->getCycleChanceFormatted());
    }

    public function testGetEfficiencyFormatted(): void
    {
        $result = new DropEfficiencyResult(0.5, 2, 0.8567);

        $this->assertEquals('85.67%', $result->getEfficiencyFormatted());
    }

    public function testFormattedValuesWithZero(): void
    {
        $result = new DropEfficiencyResult(0.0, 0, 0.0);

        $this->assertEquals('0%', $result->getCycleChanceFormatted());
        $this->assertEquals('0%', $result->getEfficiencyFormatted());
    }

    public function testFormattedValuesWithFullPercentage(): void
    {
        $result = new DropEfficiencyResult(1.0, 1, 1.0);

        $this->assertEquals('100%', $result->getCycleChanceFormatted());
        $this->assertEquals('100%', $result->getEfficiencyFormatted());
    }
}
