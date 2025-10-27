<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251026173011 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert task.due_at and plan_application.due_at from datetime to date, remove claimed fields from sub_task';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE plan_application ALTER due_at TYPE DATE');
        $this->addSql('COMMENT ON COLUMN plan_application.due_at IS \'(DC2Type:date_immutable)\'');
        $this->addSql('ALTER TABLE sub_task DROP CONSTRAINT fk_75e844e4f67e7a38');
        $this->addSql('DROP INDEX idx_subtask_claimed_by');
        $this->addSql('ALTER TABLE sub_task DROP claimed_by_id');
        $this->addSql('ALTER TABLE sub_task DROP claimed_at');
        $this->addSql('ALTER TABLE task ALTER due_at TYPE DATE');
        $this->addSql('COMMENT ON COLUMN task.due_at IS \'(DC2Type:date_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE task ALTER due_at TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('COMMENT ON COLUMN task.due_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('ALTER TABLE sub_task ADD claimed_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sub_task ADD claimed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN sub_task.claimed_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('ALTER TABLE sub_task ADD CONSTRAINT fk_75e844e4f67e7a38 FOREIGN KEY (claimed_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_subtask_claimed_by ON sub_task (claimed_by_id)');
        $this->addSql('ALTER TABLE plan_application ALTER due_at TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('COMMENT ON COLUMN plan_application.due_at IS \'(DC2Type:datetimetz_immutable)\'');
    }
}
