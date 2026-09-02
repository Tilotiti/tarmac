<?php

namespace App\Tests\Functional;

use App\Service\Import\ImportFileStorage;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImportFileStorageTest extends FunctionalTestCase
{
    public function testStoreReadDeleteAndPurge(): void
    {
        $club = $this->createClub('Club', 'club');
        $path = tempnam(sys_get_temp_dir(), 'import');
        file_put_contents($path, "Nom;Type\nF-CGAB;Planeur\n");
        $upload = new UploadedFile($path, 'materiel.csv', 'text/csv', null, true);

        $storage = static::getContainer()->get(ImportFileStorage::class);
        $key = $storage->store($upload, $club);

        $this->assertMatchesRegularExpression('#^imports/' . $club->getId() . '/[0-9a-f-]{36}\.csv$#', $key);
        $this->assertSame("Nom;Type\nF-CGAB;Planeur\n", $storage->read($key));

        $this->assertSame(0, $storage->purgeOlderThan(new \DateTimeImmutable('-1 day')));
        $this->assertSame(1, $storage->purgeOlderThan(new \DateTimeImmutable('+1 minute')));
        $storage->delete($key); // silencieux après purge
        $this->assertSame(0, $storage->purgeOlderThan(new \DateTimeImmutable('+1 minute')));
    }
}
