<?php

namespace App\Tests\Functional;

use App\Entity\Club;
use App\Entity\Enum\EquipmentOwner;
use App\Entity\Enum\EquipmentType;
use App\Entity\Equipment;
use App\Entity\Membership;
use App\Entity\User;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImportEquipmentTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // L'adaptateur S3 de test est en mémoire (oneup_flysystem.s3_filesystem) : son contenu ne
        // survit pas au redémarrage du kernel que le client de test fait par défaut entre chaque
        // requête. L'assistant d'import s'étale sur plusieurs requêtes avec le même fichier stocké
        // entre elles : on désactive le redémarrage pour rester sur le même conteneur, comme un
        // vrai navigateur face à une seule instance d'application.
        $this->client->disableReboot();
    }

    private function clubWithManager(): array
    {
        $manager = $this->createUser('manager@example.test');
        $club = $this->createClub('Planeurs de Beauce', 'beauce');
        $club->addMembership((new Membership())->setUser($manager)->setClub($club)->setIsManager(true));
        $this->entityManager()->flush();

        return [$club, $manager];
    }

    private function host(Club $club): string
    {
        return 'https://' . $club->getSubdomain() . '.' . static::getContainer()->getParameter('domain');
    }

    private function upload(string $fixture): UploadedFile
    {
        // tempnam() ne donne pas d'extension : la conserver, sinon le contrôle d'extension du
        // formulaire (et FileFormField::setValue, qui se base sur le nom du fichier temporaire
        // pour submit()) échoue même sur un fichier valide.
        $path = tempnam(sys_get_temp_dir(), 'import') . '.' . pathinfo($fixture, PATHINFO_EXTENSION);
        copy(__DIR__ . '/../fixtures/imports/' . $fixture, $path);

        return new UploadedFile($path, $fixture, 'text/csv', null, true);
    }

    public function testFullEquipmentImport(): void
    {
        [$club, $manager] = $this->clubWithManager();
        $existing = (new Equipment())->setName('F-CGAB')->setType(EquipmentType::GLIDER)->setOwner(EquipmentOwner::CLUB)->setClub($club)->setCreatedBy($manager);
        $this->entityManager()->persist($existing);
        $this->entityManager()->flush();
        $this->client->loginUser($manager);
        $host = $this->host($club);

        // Écran 1 : fichier
        $crawler = $this->client->request('GET', $host . '/import/equipments');
        $this->assertResponseIsSuccessful();
        $form = $crawler->selectButton('Continuer')->form();
        $form['import_file[file]']->upload($this->upload('materiel-semicolon.csv')->getPathname());
        $this->client->submit($form);
        $this->assertResponseRedirects('/import/equipments/colonnes');

        // Écran 2 : colonnes pré-affectées par synonymes, en-tête détecté
        $crawler = $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSame('0', $crawler->filter('select[name="columns[name]"] option[selected]')->attr('value'));
        $this->assertSame('1', $crawler->filter('select[name="columns[type]"] option[selected]')->attr('value'));
        $this->assertSame('2', $crawler->filter('select[name="columns[description]"] option[selected]')->attr('value'));
        $this->assertTrue($crawler->filter('input[name="hasHeader"]')->attr('checked') !== null);
        $this->assertSelectorTextContains('body', 'ASK 21');
        $this->client->submit($crawler->selectButton('Continuer')->form());
        $this->assertResponseRedirects('/import/equipments/valeurs');

        // Écran 2b : valeurs du type et du propriétaire. Les sélecteurs sont indexés par position
        // (0 = Planeur, 1 = Avion, 2 = Treuil pour le type ; 0 = Club, 1 = Privé pour le
        // propriétaire), pas par valeur brute — voir ImportController::values().
        $crawler = $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Planeur');
        $this->assertSame('glider', $crawler->filter('select[name="values[type][0]"] option[selected]')->attr('value'));
        $this->assertSame('facility', $crawler->filter('select[name="values[type][2]"] option[selected]')->attr('value'));
        $this->assertSame('private', $crawler->filter('select[name="values[owner][1]"] option[selected]')->attr('value'));
        $this->client->submit($crawler->selectButton('Vérifier l\'aperçu')->form());
        $this->assertResponseRedirects('/import/equipments/apercu');

        // Écran 3 : aperçu
        $crawler = $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', '3 lignes seront importées');
        $this->assertSelectorTextContains('body', 'xiste déjà dans le club');
        $this->client->submit($crawler->selectButton('Importer 3 lignes')->form());
        $this->assertResponseRedirects('/equipments');

        $names = static::getContainer()->get(\App\Repository\EquipmentRepository::class)->findActiveNamesByClub($club);
        sort($names);
        $this->assertSame(['F-CGAB', 'F-CHXY', 'F-GKLM', 'Treuil n°2'], $names);
        $treuil = $this->entityManager()->getRepository(Equipment::class)->findOneBy(['name' => 'Treuil n°2']);
        $this->assertSame(EquipmentType::FACILITY, $treuil->getType());
        $chxy = $this->entityManager()->getRepository(Equipment::class)->findOneBy(['name' => 'F-CHXY']);
        $this->assertSame(EquipmentOwner::PRIVATE, $chxy->getOwner());

        /** @var FilesystemOperator $s3 */
        $s3 = static::getContainer()->get('oneup_flysystem.s3_filesystem');
        $this->assertCount(0, iterator_to_array($s3->listContents('imports', true)), 'le fichier est supprimé après import');
    }

    public function testHeaderlessFileAndFixedType(): void
    {
        [$club, $manager] = $this->clubWithManager();
        $this->client->loginUser($manager);
        $host = $this->host($club);

        $crawler = $this->client->request('GET', $host . '/import/equipments');
        $form = $crawler->selectButton('Continuer')->form();
        $form['import_file[file]']->upload($this->upload('materiel-comma-noheader.csv')->getPathname());
        $this->client->submit($form);
        $crawler = $this->client->followRedirect();

        $this->assertNull($crawler->filter('input[name="hasHeader"]')->attr('checked'));
        $this->assertSelectorTextContains('select[name="columns[name]"]', 'A');
        $form = $crawler->selectButton('Continuer')->form();
        $form['columns[name]'] = '0';
        $form['columns[type]'] = '';
        $form['fixed[type]'] = 'glider';
        $this->client->submit($form);
        $this->assertResponseRedirects('/import/equipments/apercu');
        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('body', '2 lignes seront importées');
        $this->client->submit($crawler->selectButton('Importer 2 lignes')->form());

        $this->assertSame(2, static::getContainer()->get(\App\Repository\EquipmentRepository::class)->countActiveByClub($club));
    }

    public function testInvalidFixedValueIsRejected(): void
    {
        [$club, $manager] = $this->clubWithManager();
        $this->client->loginUser($manager);
        $host = $this->host($club);

        $crawler = $this->client->request('GET', $host . '/import/equipments');
        $form = $crawler->selectButton('Continuer')->form();
        $form['import_file[file]']->upload($this->upload('materiel-comma-noheader.csv')->getPathname());
        $this->client->submit($form);
        $crawler = $this->client->followRedirect();

        // Valeur fixe falsifiée : absente des choix du champ type (glider/airplane/facility).
        // disableValidation() car le formulaire du crawler refuse par défaut une valeur de
        // <select> hors de ses <option> — exactement ce que la requête falsifiée simule.
        $form = $crawler->selectButton('Continuer')->form();
        $form->disableValidation();
        $form['columns[name]'] = '0';
        $form['columns[type]'] = '';
        $form['fixed[type]'] = 'xyz';
        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('body', 'Choisissez une valeur pour');
    }

    public function testMemberIsForbidden(): void
    {
        $member = $this->createUser('membre@example.test');
        $club = $this->createClub('Club', 'club');
        $club->addMembership((new Membership())->setUser($member)->setClub($club));
        $this->entityManager()->flush();
        $this->client->loginUser($member);

        $this->client->request('GET', $this->host($club) . '/import/equipments');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testMissingS3FileRedirectsToFileStep(): void
    {
        [$club, $manager] = $this->clubWithManager();
        $this->client->loginUser($manager);
        $host = $this->host($club);

        $crawler = $this->client->request('GET', $host . '/import/equipments');
        $form = $crawler->selectButton('Continuer')->form();
        $form['import_file[file]']->upload($this->upload('materiel-semicolon.csv')->getPathname());
        $this->client->submit($form);
        $this->assertResponseRedirects('/import/equipments/colonnes');

        // Le fichier disparaît de S3 (purge, suppression manuelle…) entre deux requêtes de
        // l'assistant : on simule ça en le supprimant directement dans le filesystem en mémoire.
        /** @var FilesystemOperator $s3 */
        $s3 = static::getContainer()->get('oneup_flysystem.s3_filesystem');
        foreach ($s3->listContents('imports', true) as $item) {
            if ($item->isFile()) {
                $s3->delete($item->path());
            }
        }

        $this->client->request('GET', $host . '/import/equipments/colonnes');
        $this->assertResponseRedirects('/import/equipments');
        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert-danger, .tarmac-alert--danger, body', "n'est plus disponible");
    }

    public function testCancelClearsStateAndS3File(): void
    {
        [$club, $manager] = $this->clubWithManager();
        $this->client->loginUser($manager);
        $host = $this->host($club);

        $crawler = $this->client->request('GET', $host . '/import/equipments');
        $form = $crawler->selectButton('Continuer')->form();
        $form['import_file[file]']->upload($this->upload('materiel-semicolon.csv')->getPathname());
        $this->client->submit($form);
        $crawler = $this->client->followRedirect();

        $this->client->submit($crawler->selectButton("Abandonner l'import")->form());
        $this->assertResponseRedirects('/equipments');

        /** @var FilesystemOperator $s3 */
        $s3 = static::getContainer()->get('oneup_flysystem.s3_filesystem');
        $this->assertCount(0, iterator_to_array($s3->listContents('imports', true)), "le fichier est supprimé à l'abandon");

        $this->client->request('GET', $host . '/import/equipments/colonnes');
        $this->assertResponseRedirects('/import/equipments', null, 'plus de session à reprendre après abandon');
    }

    public function testBracketInRawValueIsImportableAfterMapping(): void
    {
        [$club, $manager] = $this->clubWithManager();
        $this->client->loginUser($manager);
        $host = $this->host($club);

        $crawler = $this->client->request('GET', $host . '/import/equipments');
        $form = $crawler->selectButton('Continuer')->form();
        $form['import_file[file]']->upload($this->upload('materiel-brackets.csv')->getPathname());
        $this->client->submit($form);
        $crawler = $this->client->followRedirect();

        $this->client->submit($crawler->selectButton('Continuer')->form());
        $crawler = $this->client->followRedirect(); // écran des valeurs

        $this->assertSelectorTextContains('body', 'Planeur [ancien]');
        // « Planeur [ancien] » contient « planeur » : déjà rapproché de « glider » par le
        // ValueMapper. Le champ est indexé par position (values[type][0]), jamais par la valeur
        // brute — un crochet dans un nom de champ HTML casserait le parsing des tableaux imbriqués.
        $select = $crawler->filter('select[name^="values[type]"]')->first();
        $this->assertMatchesRegularExpression('/^values\[type\]\[\d+\]$/', $select->attr('name'));
        $this->assertSame('glider', $select->filter('option[selected]')->attr('value'));

        $this->client->submit($crawler->selectButton("Vérifier l'aperçu")->form());
        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('body', '2 lignes seront importées');

        $this->client->submit($crawler->selectButton('Importer 2 lignes')->form());
        $this->assertResponseRedirects('/equipments');

        $names = static::getContainer()->get(\App\Repository\EquipmentRepository::class)->findActiveNamesByClub($club);
        sort($names);
        $this->assertSame(['F-CGAB', 'F-CHXY'], $names);
    }
}
