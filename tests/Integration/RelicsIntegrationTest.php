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

        $links = $crawler->filter('.relics-list a');
        if (0 === $links->count()) {
            $this->markTestSkipped('No relics found in the list - Meilisearch may be empty');
        }

        $link = $links->first()->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h1');
    }
}
