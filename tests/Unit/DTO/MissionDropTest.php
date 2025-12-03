<?php

namespace App\Tests\Unit\DTO;

use App\DTO\MissionDrop;
use PHPUnit\Framework\TestCase;

class MissionDropTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $drop = new MissionDrop(
            planet: 'Earth',
            mission: 'Everest',
            rotation: 'A',
            chance: 11.06,
            gameMode: 'Excavation'
        );

        $this->assertEquals('Earth', $drop->planet);
        $this->assertEquals('Everest', $drop->mission);
        $this->assertEquals('A', $drop->rotation);
        $this->assertEquals(11.06, $drop->chance);
        $this->assertEquals('Excavation', $drop->gameMode);
    }

    public function testIsReadonly(): void
    {
        $reflection = new \ReflectionClass(MissionDrop::class);

        $this->assertTrue($reflection->isReadOnly());
    }

    public function testPropertiesArePublic(): void
    {
        $reflection = new \ReflectionClass(MissionDrop::class);

        foreach (['planet', 'mission', 'rotation', 'chance', 'gameMode'] as $property) {
            $this->assertTrue($reflection->getProperty($property)->isPublic());
        }
    }
}
