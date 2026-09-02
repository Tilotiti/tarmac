<?php

namespace App\Tests\Functional;

class SmokeTest extends FunctionalTestCase
{
    public function testLandingRenders(): void
    {
        $this->client->request('GET', $this->www('/'));

        $this->assertResponseIsSuccessful();
    }

    public function testCreatedUserIsRolledBackBetweenTests(): void
    {
        $this->createUser('smoke@example.test');

        $this->assertSame(1, $this->entityManager()->getRepository(\App\Entity\User::class)->count(['email' => 'smoke@example.test']));
    }
}
