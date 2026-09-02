<?php

namespace App\Tests\Functional;

use App\Entity\Aerodrome;

class OnboardingApiTest extends FunctionalTestCase
{
    public function testAerodromeSearchIsAccentInsensitive(): void
    {
        $aerodrome = (new Aerodrome())->setSourceId(1)->setName('Megève Altiport')->setMunicipality('Megève')->setIcaoCode('LFHM')->setLatitude(45.8)->setLongitude(6.6)->refreshSearchIndex();
        $this->entityManager()->persist($aerodrome);
        $this->entityManager()->flush();
        $this->client->loginUser($this->createUser('membre@example.test'));

        $this->client->request('GET', $this->www('/api/aerodromes?q=megeve'));

        $this->assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame('Megève Altiport · LFHM', $data[0]['label']);
    }

    public function testSubdomainCheck(): void
    {
        $this->createClub('Existant', 'existant');

        $this->client->request('GET', $this->www('/creer-mon-club/sous-domaine?value=existant'));
        $this->assertFalse(json_decode((string) $this->client->getResponse()->getContent(), true)['available']);

        $this->client->request('GET', $this->www('/creer-mon-club/sous-domaine?value=admin'));
        $this->assertFalse(json_decode((string) $this->client->getResponse()->getContent(), true)['available']);

        $this->client->request('GET', $this->www('/creer-mon-club/sous-domaine?value=nouveau-club'));
        $this->assertTrue(json_decode((string) $this->client->getResponse()->getContent(), true)['available']);
    }
}
