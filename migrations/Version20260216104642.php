<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260216104642 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add CONTRIBUTED to ActivityType (no schema change - type column is VARCHAR)';
    }

    public function up(Schema $schema): void
    {
        // Activity type is stored as VARCHAR(30), no schema change needed for new enum value
    }

    public function down(Schema $schema): void
    {
        // No schema change to revert
    }
}
