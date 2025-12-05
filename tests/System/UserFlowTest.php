<?php

namespace App\Tests\System;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UserFlowTest extends WebTestCase
{
    public function testCompleteUserJourney(): void
    {
        $client = static::createClient();

        // 1. Home Page
        $crawler = $client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        // 2. Primes List
        $crawler = $client->request('GET', '/primes');
        $this->assertResponseIsSuccessful();

        // 3. Select a Prime
        $primeLinks = $crawler->filter('a[href^="/primes/"]');
        if (0 === $primeLinks->count()) {
            $this->markTestSkipped('No primes found - Meilisearch may be empty');
        }

        $link = $primeLinks->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseIsSuccessful();

        // 4. Check details and maybe click a Relic
        $relicLinks = $crawler->filter('a[href^="/relics/"]');
        if ($relicLinks->count() > 0) {
            $link = $relicLinks->first()->link();
            $crawler = $client->click($link);
            $this->assertResponseIsSuccessful();
        }
    }
}
