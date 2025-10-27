<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251026152247 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove difficulty, due date, and inspection fields from SubTask and PlanSubTask entities';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE plan_sub_task DROP difficulty');
        $this->addSql('ALTER TABLE plan_sub_task DROP relative_due_days');
        $this->addSql('ALTER TABLE sub_task DROP CONSTRAINT fk_75e844e4475ea6be');
        $this->addSql('DROP INDEX idx_75e844e4475ea6be');
        $this->addSql('DROP INDEX idx_subtask_difficulty');
        $this->addSql('DROP INDEX idx_subtask_due_at');
        $this->addSql('ALTER TABLE sub_task DROP inspected_by_id');
        $this->addSql('ALTER TABLE sub_task DROP due_at');
        $this->addSql('ALTER TABLE sub_task DROP difficulty');
        $this->addSql('ALTER TABLE sub_task DROP inspected_at');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE sub_task ADD inspected_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sub_task ADD due_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE sub_task ADD difficulty SMALLINT NOT NULL');
        $this->addSql('ALTER TABLE sub_task ADD inspected_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN sub_task.due_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN sub_task.inspected_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('ALTER TABLE sub_task ADD CONSTRAINT fk_75e844e4475ea6be FOREIGN KEY (inspected_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_75e844e4475ea6be ON sub_task (inspected_by_id)');
        $this->addSql('CREATE INDEX idx_subtask_difficulty ON sub_task (difficulty)');
        $this->addSql('CREATE INDEX idx_subtask_due_at ON sub_task (due_at)');
        $this->addSql('ALTER TABLE plan_sub_task ADD difficulty SMALLINT NOT NULL');
        $this->addSql('ALTER TABLE plan_sub_task ADD relative_due_days INT DEFAULT NULL');
    }
}
