<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251024144123 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE equipment_user (equipment_id INT NOT NULL, user_id INT NOT NULL, PRIMARY KEY(equipment_id, user_id))');
        $this->addSql('CREATE INDEX IDX_B717074F517FE9FE ON equipment_user (equipment_id)');
        $this->addSql('CREATE INDEX IDX_B717074FA76ED395 ON equipment_user (user_id)');
        $this->addSql('ALTER TABLE equipment_user ADD CONSTRAINT FK_B717074F517FE9FE FOREIGN KEY (equipment_id) REFERENCES equipment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE equipment_user ADD CONSTRAINT FK_B717074FA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE equipment ADD is_private BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE equipment ALTER COLUMN is_private DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE equipment_user DROP CONSTRAINT FK_B717074F517FE9FE');
        $this->addSql('ALTER TABLE equipment_user DROP CONSTRAINT FK_B717074FA76ED395');
        $this->addSql('DROP TABLE equipment_user');
        $this->addSql('ALTER TABLE equipment DROP is_private');
    }
}
