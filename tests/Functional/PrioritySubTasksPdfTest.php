<?php

namespace App\Tests\Functional;

use App\Entity\Membership;

class PrioritySubTasksPdfTest extends FunctionalTestCase
{
    public function testWelcomeMessageCannotRunPhpInPdf(): void
    {
        $manager = $this->createUser('gestionnaire@example.test');
        $club = $this->createClub('Club', 'club');
        $club->addMembership((new Membership())->setUser($manager)->setClub($club)->setIsManager(true));
        $club->setWelcomeMessage("Bonjour\n\n<script type=\"text/php\">throw new \\LogicException('php executed');</script>\n");
        $this->entityManager()->flush();
        $this->client->loginUser($manager);
        $url = 'https://club.' . static::getContainer()->getParameter('domain') . '/print-priority-subtasks';

        $this->client->request('GET', $url . '?preview=1');
        $this->assertStringNotContainsString('<script type="text/php">', $this->client->getResponse()->getContent());

        $this->client->request('GET', $url);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $this->client->getResponse()->getContent());
    }
}
