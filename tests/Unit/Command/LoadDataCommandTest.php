<?php

namespace App\Tests\Unit\Command;

use App\Command\LoadDataCommand;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class LoadDataCommandTest extends TestCase
{
    private string $tempDir;
    /** @var Client&\PHPUnit\Framework\MockObject\MockObject */
    private $mockMeiliClient;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/equinoxer_test_'.uniqid();
        mkdir($this->tempDir, 0777, true);

        file_put_contents($this->tempDir.'/Relics_Normalized.json', json_encode([
            ['slug' => 'lith_a1', 'name' => 'Lith A1 Relic'],
        ]));
        file_put_contents($this->tempDir.'/Primes_Normalized.json', json_encode([
            ['slug' => 'ash_prime', 'name' => 'Ash Prime'],
        ]));
        file_put_contents($this->tempDir.'/missionRewards.json', json_encode([
            'missionRewards' => [],
        ]));

        $mockIndex = $this->createMock(Indexes::class);
        $mockIndex->method('deleteAllDocuments')->willReturn(['taskUid' => 1]);
        $mockIndex->method('updateSettings')->willReturn(['taskUid' => 2]);
        $mockIndex->method('addDocuments')->willReturn(['taskUid' => 3]);

        $this->mockMeiliClient = $this->createMock(Client::class);
        $this->mockMeiliClient->method('index')->willReturn($mockIndex);
        $this->mockMeiliClient->method('waitForTask')->willReturn(['status' => 'succeeded']);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("$this->tempDir/*.*"));
        @rmdir($this->tempDir);
    }

    public function testExecuteLoadsDataSuccessfully(): void
    {
        $command = new LoadDataCommand($this->mockMeiliClient, $this->tempDir);

        $application = new Application();
        $application->add($command);
        $commandTester = new CommandTester($application->find('app:load-data'));

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Loading data from local JSON files', $output);
        $this->assertStringContainsString('Indexed 1 relics from JSON', $output);
        $this->assertStringContainsString('Indexed 1 primes from JSON', $output);
        $this->assertStringContainsString('Data load complete.', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    public function testExecuteHandlesMissingFiles(): void
    {
        @unlink($this->tempDir.'/Relics_Normalized.json');

        $command = new LoadDataCommand($this->mockMeiliClient, $this->tempDir);

        $application = new Application();
        $application->add($command);
        $commandTester = new CommandTester($application->find('app:load-data'));

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Relics_Normalized.json not found', $output);
    }
}
