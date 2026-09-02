<?php

namespace App\Tests\Functional;

use App\Entity\Invitation;
use App\Entity\Membership;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImportInvitationTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // L'adaptateur S3 de test est en mémoire (oneup_flysystem.s3_filesystem) : son contenu ne
        // survit pas au redémarrage du kernel que le client de test fait par défaut entre chaque
        // requête. L'assistant d'import s'étale sur plusieurs requêtes avec le même fichier stocké
        // entre elles : on désactive le redémarrage pour rester sur le même conteneur.
        $this->client->disableReboot();
    }

    private function upload(string $fixture): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import') . '.' . pathinfo($fixture, PATHINFO_EXTENSION);
        copy(__DIR__ . '/../fixtures/imports/' . $fixture, $path);

        return new UploadedFile($path, $fixture, 'text/csv', null, true);
    }

    public function testGivavFileWithRolesAtPreview(): void
    {
        $manager = $this->createUser('manager@example.test');
        $club = $this->createClub('Planeurs de Beauce', 'beauce');
        $club->addMembership((new Membership())->setUser($manager)->setClub($club)->setIsManager(true));
        $this->entityManager()->flush();
        $this->client->loginUser($manager);
        $host = 'https://beauce.' . static::getContainer()->getParameter('domain');

        $crawler = $this->client->request('GET', $host . '/import/invitations');
        $form = $crawler->selectButton('Continuer')->form();
        $form['import_file[file]']->upload($this->upload('membres-givav.csv')->getPathname());
        $this->client->submit($form);
        $crawler = $this->client->followRedirect();
        $this->assertSame('2', $crawler->filter('select[name="columns[email]"] option[selected]')->attr('value'));
        $this->assertSame('1', $crawler->filter('select[name="columns[firstname]"] option[selected]')->attr('value'));
        $this->assertSame('0', $crawler->filter('select[name="columns[lastname]"] option[selected]')->attr('value'));
        $this->assertSame('3', $crawler->filter('select[name="columns[isInspector]"] option[selected]')->attr('value'));
        $this->client->submit($crawler->selectButton('Continuer')->form());
        $crawler = $this->client->followRedirect(); // valeurs : « oui » → yes
        $this->assertSame('yes', $crawler->filter('select[name="values[isInspector][oui]"] option[selected]')->attr('value'));
        $this->client->submit($crawler->selectButton('Vérifier l\'aperçu')->form());
        $crawler = $this->client->followRedirect();

        $this->assertSelectorTextContains('body', '2 lignes seront importées');
        $this->assertTrue($crawler->filter('input[name="rows[1][isInspector]"]')->attr('checked') !== null, 'rôle pré-coché depuis le fichier');
        $form = $crawler->selectButton('Importer 2 lignes')->form();
        $form['rows[2][isManager]']->tick();
        $this->client->submit($form);
        $this->assertResponseRedirects('/invitations');

        $this->assertEmailCount(2);
        $invitations = $this->entityManager()->getRepository(Invitation::class)->findBy(['club' => $club], ['email' => 'ASC']);
        $this->assertCount(2, $invitations);
        $this->assertSame('claire@example.test', $invitations[0]->getEmail());
        $this->assertTrue($invitations[0]->isManager());
        $this->assertSame('jean@example.test', $invitations[1]->getEmail());
        $this->assertTrue($invitations[1]->isInspector());
        $this->assertFalse($invitations[1]->isManager());
    }

    public function testOldImportRouteIsGone(): void
    {
        $manager = $this->createUser('manager@example.test');
        $club = $this->createClub('Club', 'club');
        $club->addMembership((new Membership())->setUser($manager)->setClub($club)->setIsManager(true));
        $this->entityManager()->flush();
        $this->client->loginUser($manager);

        $this->client->request('GET', 'https://club.' . static::getContainer()->getParameter('domain') . '/invitations/import');

        $this->assertResponseStatusCodeSame(404);
    }
}
