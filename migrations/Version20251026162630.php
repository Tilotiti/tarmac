<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251026162630 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sub_task DROP CONSTRAINT fk_75e844e4f67e7a38');
        $this->addSql('DROP INDEX idx_75e844e4f67e7a38');
        $this->addSql('ALTER TABLE sub_task DROP claimed_by_id');
        $this->addSql('ALTER TABLE sub_task DROP claimed_at');
        $this->addSql('ALTER TABLE task DROP CONSTRAINT fk_527edb25f67e7a38');
        $this->addSql('DROP INDEX idx_527edb25f67e7a38');
        $this->addSql('ALTER TABLE task DROP claimed_by_id');
        $this->addSql('ALTER TABLE task DROP claimed_at');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE task ADD claimed_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE task ADD claimed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN task.claimed_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT fk_527edb25f67e7a38 FOREIGN KEY (claimed_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_527edb25f67e7a38 ON task (claimed_by_id)');
        $this->addSql('ALTER TABLE sub_task ADD claimed_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sub_task ADD claimed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN sub_task.claimed_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('ALTER TABLE sub_task ADD CONSTRAINT fk_75e844e4f67e7a38 FOREIGN KEY (claimed_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_75e844e4f67e7a38 ON sub_task (claimed_by_id)');
    }
}
