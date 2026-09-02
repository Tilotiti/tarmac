<?php

namespace App\Tests\Functional;

class LandingTest extends FunctionalTestCase
{
    public function testAnonymousVisitorSeesCreateClubCallToAction(): void
    {
        $crawler = $this->client->request('GET', $this->www('/'));

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThanOrEqual(2, $crawler->filter('a[href$="/creer-mon-club"]')->count());
        $this->assertSelectorTextContains('body', 'Gratuit pour les associations');
        $this->assertSelectorTextNotContains('body', 'Tarmac fonctionne sur invitation');
    }

    public function testLoggedInUserSeesMyClubsFirst(): void
    {
        $this->client->loginUser($this->createUser('membre@example.test'));
        $crawler = $this->client->request('GET', $this->www('/'));

        $this->assertSame(1, $crawler->filter('.landing-hero a.landing-btn-primary')->count());
        $this->assertStringContainsString('/clubs', $crawler->filter('.landing-hero a.landing-btn-primary')->attr('href'));
    }
}
