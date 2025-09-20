<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RelicsIntegrationTest extends WebTestCase
{
    public function testShowRelic(): void
    {
        $client = static::createClient();
        
        $crawler = $client->request('GET', '/relics'); 
        $this->assertResponseIsSuccessful();
        
        $link = $crawler->filter('.relics-list a')->first()->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h1');
    }
}
