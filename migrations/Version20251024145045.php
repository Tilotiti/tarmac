<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251024145045 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // Add owner column with default value
        $this->addSql('ALTER TABLE equipment ADD owner VARCHAR(255) NOT NULL DEFAULT \'club\'');

        // Migrate existing data: is_private = false -> 'club', is_private = true -> 'private'
        $this->addSql('UPDATE equipment SET owner = \'private\' WHERE is_private = true');
        $this->addSql('UPDATE equipment SET owner = \'club\' WHERE is_private = false');

        // Drop old column and default
        $this->addSql('ALTER TABLE equipment DROP is_private');
        $this->addSql('ALTER TABLE equipment ALTER COLUMN owner DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');

        // Add back is_private column with default
        $this->addSql('ALTER TABLE equipment ADD is_private BOOLEAN NOT NULL DEFAULT FALSE');

        // Migrate data back: 'private' -> true, 'club' -> false
        $this->addSql('UPDATE equipment SET is_private = true WHERE owner = \'private\'');
        $this->addSql('UPDATE equipment SET is_private = false WHERE owner = \'club\'');

        // Drop owner column and default
        $this->addSql('ALTER TABLE equipment DROP owner');
        $this->addSql('ALTER TABLE equipment ALTER COLUMN is_private DROP DEFAULT');
    }
}
