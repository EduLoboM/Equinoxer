<?php

namespace App\Tests\System;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Panther\PantherTestCase;

#[Group('panther')]
class UpAndLoadDataTest extends PantherTestCase
{
    public function testUpdateDataFlow(): void
    {
        $client = static::createPantherClient([
            'browser' => static::CHROME,
            'connection_timeout_in_ms' => 600000,
            'request_timeout_in_ms' => 600000,
        ]);

        $crawler = $client->request('GET', '/');

        $this->assertSelectorExists('.btn-update');

        $client->clickLink('Update Data');

        $client->waitFor('h1', 600);

        $this->assertSelectorTextContains('h1', 'DATA UPDATE RESULT');
    }

    public function testLoadDataFlow(): void
    {
        $client = static::createPantherClient([
            'browser' => static::CHROME,
            'connection_timeout_in_ms' => 600000,
            'request_timeout_in_ms' => 600000,
        ]);

        $crawler = $client->request('GET', '/');

        $this->assertSelectorExists('.btn-load');

        $client->clickLink('Load Data');

        $client->waitFor('h1', 600);

        $this->assertSelectorTextContains('h1', 'DATA LOAD RESULT');
    }
}
