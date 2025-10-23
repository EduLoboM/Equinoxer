<?php

namespace App\Tests\Unit\Service;

use App\Service\DropEfficiencyCalculator;
use PHPUnit\Framework\TestCase;

class DropEfficiencyCalculatorTest extends TestCase
{
    private DropEfficiencyCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new DropEfficiencyCalculator();
    }

    public function testSingleChance100Percent(): void
    {
        $result = $this->calculator->calculate([100.0], 'A');

        $this->assertEquals(1.0, $result->cycleChance);
        $this->assertEquals(2, $result->missionsUsed);
        $this->assertEquals(0.5, $result->efficiency);
    }

    public function testZeroChance(): void
    {
        $result = $this->calculator->calculate([0.0], 'A');

        $this->assertEquals(0.0, $result->cycleChance);
        $this->assertEquals(2, $result->missionsUsed);
        $this->assertEquals(0.0, $result->efficiency);
    }

    public function testMultipleChancesAdditive(): void
    {
        $result = $this->calculator->calculate([50.0, 50.0], 'A');

        $this->assertEquals(0.75, $result->cycleChance);
    }

    public function testRotationAMissionCount(): void
    {
        $result = $this->calculator->calculate([10.0], 'A');

        $this->assertEquals(2, $result->missionsUsed);
    }

    public function testRotationBMissionCount(): void
    {
        $result = $this->calculator->calculate([10.0], 'B');

        $this->assertEquals(3, $result->missionsUsed);
    }

    public function testRotationCMissionCount(): void
    {
        $result = $this->calculator->calculate([10.0], 'C');

        $this->assertEquals(4, $result->missionsUsed);
    }

    public function testEfficiencyCalculation(): void
    {
        $result = $this->calculator->calculate([25.0], 'C');

        $this->assertEquals(0.25, $result->cycleChance);
        $this->assertEquals(4, $result->missionsUsed);
        $this->assertEquals(0.0625, $result->efficiency);
    }

    public function testFromChanceStringsWithPercentSign(): void
    {
        $result = $this->calculator->calculateFromChanceStrings(['50%', '25%'], 'A');

        $expectedCycleChance = 1 - (0.5 * 0.75);
        $this->assertEquals($expectedCycleChance, $result->cycleChance);
    }

    public function testFormattedOutputRounding(): void
    {
        $result = $this->calculator->calculate([33.333], 'A');

        $this->assertMatchesRegularExpression('/^\d+\.\d{1,2}%$/', $result->getCycleChanceFormatted());
        $this->assertMatchesRegularExpression('/^\d+\.\d{1,2}%$/', $result->getEfficiencyFormatted());
    }

    public function testUnknownRotationDefaultsToThree(): void
    {
        $result = $this->calculator->calculate([10.0], 'X');

        $this->assertEquals(4, $result->missionsUsed);
    }
}
