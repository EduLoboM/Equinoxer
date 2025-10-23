<?php

namespace App\Tests\Unit\ValueObject;

use App\ValueObject\WarframeItemName;
use PHPUnit\Framework\TestCase;

class WarframeItemNameTest extends TestCase
{
    public function testGetCleanNameRemovesIntact(): void
    {
        $name = new WarframeItemName('Lith A1 Intact');

        $this->assertEquals('Lith A1', $name->getCleanName());
    }

    public function testGetCleanNameRemovesRadiant(): void
    {
        $name = new WarframeItemName('Neo V8 (Radiant)');

        $this->assertEquals('Neo V8', $name->getCleanName());
    }

    public function testGetCleanNameRemovesFlawless(): void
    {
        $name = new WarframeItemName('Axi P5 Flawless');

        $this->assertEquals('Axi P5', $name->getCleanName());
    }

    public function testGetCleanNameRemovesExceptional(): void
    {
        $name = new WarframeItemName('Meso G2 (Exceptional)');

        $this->assertEquals('Meso G2', $name->getCleanName());
    }

    public function testGetTierReturnsLith(): void
    {
        $name = new WarframeItemName('Lith A1 Relic');

        $this->assertEquals('Lith', $name->getTier());
    }

    public function testGetTierReturnsMeso(): void
    {
        $name = new WarframeItemName('Meso B3');

        $this->assertEquals('Meso', $name->getTier());
    }

    public function testGetTierReturnsNeo(): void
    {
        $name = new WarframeItemName('Neo C4 Radiant');

        $this->assertEquals('Neo', $name->getTier());
    }

    public function testGetTierReturnsAxi(): void
    {
        $name = new WarframeItemName('Axi D5');

        $this->assertEquals('Axi', $name->getTier());
    }

    public function testGetTierReturnsRequiem(): void
    {
        $name = new WarframeItemName('Requiem I');

        $this->assertEquals('Requiem', $name->getTier());
    }

    public function testGetTierReturnsNullForUnknown(): void
    {
        $name = new WarframeItemName('Unknown Item');

        $this->assertNull($name->getTier());
    }

    public function testIsRelicWithRelicSuffix(): void
    {
        $name = new WarframeItemName('Lith A1 Relic');

        $this->assertTrue($name->isRelic());
    }

    public function testIsRelicWithTierOnly(): void
    {
        $name = new WarframeItemName('Meso B3 Intact');

        $this->assertTrue($name->isRelic());
    }

    public function testIsRelicReturnsFalseForNonRelic(): void
    {
        $name = new WarframeItemName('Braton Prime Blueprint');

        $this->assertFalse($name->isRelic());
    }

    public function testGetSlugForLithRelic(): void
    {
        $name = new WarframeItemName('Lith A1 Intact');

        $this->assertEquals('lith_a1', $name->getSlug());
    }

    public function testGetSlugForAxiRelic(): void
    {
        $name = new WarframeItemName('Axi P5 Radiant');

        $this->assertEquals('axi_p5', $name->getSlug());
    }

    public function testGetRawName(): void
    {
        $rawName = 'Lith A1 (Radiant)';
        $name = new WarframeItemName($rawName);

        $this->assertEquals($rawName, $name->getRawName());
    }
}
