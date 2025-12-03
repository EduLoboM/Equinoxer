<?php

namespace App\Tests\Unit\DTO;

use App\DTO\RelicDrop;
use PHPUnit\Framework\TestCase;

class RelicDropTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $drop = new RelicDrop(
            item: 'Ash Prime Blueprint',
            rarity: 'Rare',
            chance: 2.0
        );

        $this->assertEquals('Ash Prime Blueprint', $drop->item);
        $this->assertEquals('Rare', $drop->rarity);
        $this->assertEquals(2.0, $drop->chance);
    }

    public function testIsReadonly(): void
    {
        $reflection = new \ReflectionClass(RelicDrop::class);

        $this->assertTrue($reflection->isReadOnly());
    }

    public function testPropertiesArePublic(): void
    {
        $reflection = new \ReflectionClass(RelicDrop::class);

        foreach (['item', 'rarity', 'chance'] as $property) {
            $this->assertTrue($reflection->getProperty($property)->isPublic());
        }
    }

    public function testWithDifferentRarities(): void
    {
        $common = new RelicDrop('Forma Blueprint', 'Common', 25.33);
        $uncommon = new RelicDrop('Ash Prime Neuroptics', 'Uncommon', 11.0);
        $rare = new RelicDrop('Ash Prime Blueprint', 'Rare', 2.0);

        $this->assertEquals('Common', $common->rarity);
        $this->assertEquals('Uncommon', $uncommon->rarity);
        $this->assertEquals('Rare', $rare->rarity);
    }
}
