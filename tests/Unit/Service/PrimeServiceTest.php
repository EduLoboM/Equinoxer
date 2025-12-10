<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;


use App\Service\DropEfficiencyCalculator;
use App\Service\JsonLoader;
use App\Service\PrimeService;
use App\Service\WarframeLoot;
use App\DTO\DropEfficiencyResult;
use PHPUnit\Framework\TestCase;

class PrimeServiceTest extends TestCase
{
    private $jsonLoader;
    private $warframeLoot;
    private $calculator;
    private $primeService;

    protected function setUp(): void
    {
        $this->jsonLoader = $this->createMock(JsonLoader::class);
        $this->warframeLoot = $this->createMock(WarframeLoot::class);
        $this->calculator = $this->createMock(DropEfficiencyCalculator::class);

        $this->primeService = new PrimeService(
            $this->jsonLoader,
            $this->warframeLoot,
            $this->calculator
        );
    }

    public function testGetPrimeDetailsReturnsNullWhenNotFound(): void
    {
        $this->jsonLoader->method('load')
            ->willReturn([]);

        $result = $this->primeService->getPrimeDetails('unknown-slug');

        $this->assertNull($result);
    }

    public function testGetPrimeDetailsReturnsCorrectData(): void
    {
        $slug = 'prime_slug';
        $primeData = [
            'slug' => $slug,
            'name' => 'Test Prime',
            'parts' => ['Part1', 'Part2'],
        ];

        $relicData = [
            ['name' => 'Lith T1', 'link' => 'url'],
        ];

        $missionData = [
            [
                'planet' => 'Void',
                'mission' => 'Capture',
                'gameMode' => 'Capture',
                'rotation' => 'A',
                'chance' => '10.00',
            ],
        ];

        $efficiencyResult = new DropEfficiencyResult(
            1.5,
            100, // time
            1.5 // score
        );

        $this->jsonLoader->method('load')
            ->willReturn([$primeData]);
        $this->jsonLoader->method('findRelicsByItem')
            ->willReturn($relicData);

        $this->warframeLoot->method('getMissionsForRelic')
            ->willReturn($missionData);

        $this->calculator->method('calculateFromChanceStrings')
            ->willReturn($efficiencyResult);

        $result = $this->primeService->getPrimeDetails($slug);

        $this->assertNotNull($result);
        $this->assertEquals($primeData, $result['prime']);
        $this->assertCount(2, $result['parts']);
        $this->assertEquals('Part1', $result['parts'][0]['name']);
        $relicTry = $result['parts'][0]['relics'][0]['dropsGrouped'][0];
        $this->assertEquals('1.5', $relicTry['cycleChance']);
        $this->assertEquals('1.50%', $relicTry['efficiency']);
        $this->assertEquals('AA', $relicTry['rotationPattern']);
    }
}
