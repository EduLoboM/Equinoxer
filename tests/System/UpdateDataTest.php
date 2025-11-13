<?php

namespace App\Tests\System;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Panther\PantherTestCase;

#[Group('panther')]
class UpdateDataTest extends PantherTestCase
{
    public function testUpdateDataFlow(): void
    {
        $client = static::createPantherClient(['browser' => static::CHROME]);

        $crawler = $client->request('GET', '/');

        $this->assertSelectorExists('.btn-update');

        $client->clickLink('Update Data');

        $this->assertSelectorTextContains('h1', 'DATA UPDATE RESULT');
    }
}
