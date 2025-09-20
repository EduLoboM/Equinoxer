<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ListsIntegrationTest extends WebTestCase
{
    public function testRelicsList(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/relics');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.relics-list');
    }

    public function testPrimesList(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/primes');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h1');
        $this->assertAnySelectorTextContains('h1', 'Primes');
        $this->assertSelectorExists('.primes-list');
    }
}
