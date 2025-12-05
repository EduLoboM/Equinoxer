<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PrimesIntegrationTest extends WebTestCase
{
    public function testShowPrime(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/primes');
        $this->assertResponseIsSuccessful();

        $links = $crawler->filter('.primes-list a');
        if (0 === $links->count()) {
            $this->markTestSkipped('No primes found in the list - Meilisearch may be empty');
        }

        $link = $links->first()->link();
        $client->click($link);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h1');
    }
}
