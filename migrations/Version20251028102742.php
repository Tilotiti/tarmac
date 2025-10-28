<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251028102742 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_pilote column to invitation table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE invitation ADD is_pilote BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE invitation ALTER is_pilote DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE invitation DROP is_pilote');
    }
}
