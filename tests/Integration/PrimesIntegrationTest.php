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

        $link = $crawler->filter('.primes-list a')->first()->link();
        $client->click($link);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h1');
    }
}
