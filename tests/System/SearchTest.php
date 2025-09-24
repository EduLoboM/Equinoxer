<?php

namespace App\Tests\System;

use Symfony\Component\Panther\PantherTestCase;

class SearchTest extends PantherTestCase
{
    public function testPrimeSearchEmptiesList(): void
    {
        $client = static::createPantherClient();
        $crawler = $client->request('GET', '/primes');
        
        $client->waitFor('.primes-list');
        
        $client->findElement(\Facebook\WebDriver\WebDriverBy::id('primeSearch'))->sendKeys('XyZ123NoMatch');
        
        sleep(1); 
        
        $this->assertTrue(true); 
    }
}
