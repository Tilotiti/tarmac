<?php

namespace App\Service\Import;

use App\Entity\Club;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

/**
 * Le fichier importé vit sur S3 le temps de l'assistant : aucun disque local,
 * un autre serveur peut reprendre la session.
 */
final class ImportFileStorage
{
    private const PREFIX = 'imports/';

    public function __construct(private readonly FilesystemOperator $s3Filesystem)
    {
    }

    public function store(UploadedFile $file, Club $club): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'csv');
        $key = sprintf('%s%d/%s.%s', self::PREFIX, $club->getId(), Uuid::v4()->toRfc4122(), $extension);
        $stream = fopen($file->getPathname(), 'r');
        try {
            $this->s3Filesystem->writeStream($key, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $key;
    }

    public function read(string $key): string
    {
        return $this->s3Filesystem->read($key);
    }

    public function delete(string $key): void
    {
        if ($this->s3Filesystem->fileExists($key)) {
            $this->s3Filesystem->delete($key);
        }
    }

    public function purgeOlderThan(\DateTimeImmutable $before): int
    {
        $deleted = 0;
        foreach ($this->s3Filesystem->listContents(self::PREFIX, true) as $item) {
            /** @var StorageAttributes $item */
            if (!$item->isFile() || $item->lastModified() === null || $item->lastModified() >= $before->getTimestamp()) {
                continue;
            }
            $this->s3Filesystem->delete($item->path());
            $deleted++;
        }

        return $deleted;
    }
}
