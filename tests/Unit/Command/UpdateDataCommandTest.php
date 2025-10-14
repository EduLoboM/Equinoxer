<?php

namespace App\Tests\Unit\Command;

use App\Command\UpdateDataCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class UpdateDataCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/equinoxer_test_'.uniqid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        // Cleanup temp files
        array_map('unlink', glob("$this->tempDir/*.*"));
        rmdir($this->tempDir);
    }

    public function testExecuteUpdatesDataSuccessfully(): void
    {
        // 1. Mock HttpClient
        $mockResponseDrops = $this->createMock(ResponseInterface::class);
        $mockResponseDrops->method('getStatusCode')->willReturn(200);
        $mockResponseDrops->method('toArray')->willReturn([
            ['place' => 'Void Relic', 'item' => 'Forma', 'rarity' => 'Common', 'chance' => 25.33],
        ]);

        $mockResponseItems = $this->createMock(ResponseInterface::class);
        $mockResponseItems->method('getStatusCode')->willReturn(200);
        $mockResponseItems->method('toArray')->willReturn([
            [
                'name' => 'Lith G1 Relic',
                'category' => 'Relics',
                'rewards' => [
                    ['itemName' => 'Forma', 'rarity' => 'Common', 'chance' => 25.33],
                ],
            ],
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturnCallback(function ($method, $url, $options = []) use ($mockResponseDrops, $mockResponseItems) {
            if (str_contains($url, '/drops')) {
                return $mockResponseDrops;
            }

            return $mockResponseItems;
        });

        // 2. Instantiate Command with temp dir
        $command = new UpdateDataCommand($httpClient, $this->tempDir);

        // 3. Execute
        $application = new Application();
        $application->add($command);
        $command = $application->find('app:update-data');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        // 4. Assertions
        $this->assertStringContainsString('Data update complete.', $output);
        $this->assertFileExists($this->tempDir.'/Relics_Normalized.json');
        $this->assertFileExists($this->tempDir.'/Primes_Normalized.json');
    }
}
