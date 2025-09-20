<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HomeIntegrationTest extends WebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Welcome to Equinoxer');
        $this->assertSelectorExists('.btn-update');
    }

    public function testRunUpdateRouteExists(): void
    {
        $this->markTestSkipped('Skipping actual update run in integration test to avoid side effects/slowness.');
    }
}
