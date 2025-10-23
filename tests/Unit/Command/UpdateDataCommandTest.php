<?php

namespace App\Tests\Unit\Command;

use App\Command\UpdateDataCommand;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class UpdateDataCommandTest extends TestCase
{
    private string $tempDir;
    /** @var Client&\PHPUnit\Framework\MockObject\MockObject */
    private $mockMeiliClient;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/equinoxer_test_'.uniqid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }

        file_put_contents($this->tempDir.'/Relics_Normalized.json', '[]');
        file_put_contents($this->tempDir.'/Primes_Normalized.json', '[]');

        $mockIndex = $this->createMock(Indexes::class);
        $mockIndex->method('deleteAllDocuments')->willReturn(['taskUid' => 1]);
        $mockIndex->method('updateSettings')->willReturn(['taskUid' => 2]);
        $mockIndex->method('addDocuments')->willReturn(['taskUid' => 3]);

        $this->mockMeiliClient = $this->createMock(Client::class);
        $this->mockMeiliClient->method('index')->willReturn($mockIndex);
        $this->mockMeiliClient->method('waitForTask')->willReturn(['status' => 'succeeded', 'details' => ['indexedDocuments' => 1]]);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("$this->tempDir/*.*"));
        @rmdir($this->tempDir);
    }

    public function testExecuteFallsBackToLocalFiles(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(502);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($mockResponse);

        $command = new UpdateDataCommand($httpClient, $this->mockMeiliClient, $this->tempDir);

        $application = new Application();
        $application->add($command);
        $command = $application->find('app:update-data');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Falling back to local', $output);
        $this->assertStringContainsString('Data update complete.', $output);
    }
}
