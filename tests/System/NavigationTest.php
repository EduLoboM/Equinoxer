<?php

namespace App\Tests\System;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Panther\PantherTestCase;

#[Group('panther')]
class NavigationTest extends PantherTestCase
{
    public function testNavigationFlow(): void
    {
        $client = static::createPantherClient(['browser' => static::CHROME]);

        $crawler = $client->request('GET', '/');
        $this->assertSelectorTextContains('h1', 'WELCOME TO EQUINOXER');

        $client->clickLink('Primes List');
        $this->assertSelectorTextContains('h1', 'PRIMES LIST');

        $client->clickLink('Back to Home');
        $this->assertSelectorTextContains('h1', 'WELCOME TO EQUINOXER');

        $client->clickLink('Relics List');
        $this->assertSelectorTextContains('h1', 'RELICS LIST');
    }
}
