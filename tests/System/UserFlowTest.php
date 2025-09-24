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
        // Likely a link to Primes
        //$link = $crawler->selectLink('Primes')->link();
        //$crawler = $client->click($link);
        
        // 2. Primes List
        $crawler = $client->request('GET', '/primes');
        $this->assertResponseIsSuccessful();
        
        // 3. Select a Prime
        // Find a link to a prime detail.
        // Assuming the list has links like /primes/{slug}
        $link = $crawler->filter('a[href^="/primes/"]')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseIsSuccessful();
        
        // 4. Check details and maybe click a Relic
        // Assuming the prime page lists relics
        if ($crawler->filter('a[href^="/relics/"]')->count() > 0) {
            $link = $crawler->filter('a[href^="/relics/"]')->first()->link();
            $crawler = $client->click($link);
            $this->assertResponseIsSuccessful();
        }
    }
}
