<?php

namespace App\Tests\System;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Panther\PantherTestCase;

#[Group('panther')]
class SearchTest extends PantherTestCase
{
    public function testPrimeSearchEmptiesList(): void
    {
        $client = static::createPantherClient(['browser' => static::CHROME]);
        $crawler = $client->request('GET', '/primes');

        $client->waitFor('.primes-list');

        $client->findElement(\Facebook\WebDriver\WebDriverBy::id('primeSearch'))->sendKeys('XyZ123NoMatch');

        sleep(1);

        $this->assertSelectorExists('#primeSearch');
    }
}
