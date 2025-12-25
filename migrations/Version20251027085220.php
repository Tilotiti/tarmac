<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251027085220 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix missing activity_id_seq sequence that was dropped during table rename';
    }

    public function up(Schema $schema): void
    {
        // Check if sequence already exists
        $sequenceExists = $this->connection->fetchOne(
            "SELECT EXISTS (SELECT FROM pg_sequences WHERE schemaname = 'public' AND sequencename = 'activity_id_seq')"
        );

        if (!$sequenceExists) {
            // Create sequence only if it doesn't exist
            $this->addSql('CREATE SEQUENCE activity_id_seq');
        }

        // Set the sequence value and default (these are safe to run even if sequence already exists)
        $this->addSql('SELECT setval(\'activity_id_seq\', GREATEST(COALESCE((SELECT MAX(id) FROM activity), 0), 1))');
        $this->addSql('ALTER TABLE activity ALTER id SET DEFAULT nextval(\'activity_id_seq\')');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE activity ALTER id DROP DEFAULT');
    }
}
