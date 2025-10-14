<?php

namespace App\Tests\System;

use Symfony\Component\Panther\PantherTestCase;

class UpdateDataTest extends PantherTestCase
{
    public function testUpdateDataFlow(): void
    {
        $client = static::createPantherClient();

        $crawler = $client->request('GET', '/');

        $this->assertSelectorExists('.btn-update');

        $client->clickLink('Update Data');

        $this->assertSelectorTextContains('h1', 'DATA UPDATE RESULT');
    }
}
