<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251028093930 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move difficulty and requiresInspection from PlanTask to PlanSubTask templates';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // Add new columns to plan_sub_task with default values first
        $this->addSql('ALTER TABLE plan_sub_task ADD difficulty SMALLINT DEFAULT 3 NOT NULL');
        $this->addSql('ALTER TABLE plan_sub_task ADD requires_inspection BOOLEAN DEFAULT FALSE NOT NULL');

        // Migrate existing data from plan_task to its subtasks
        $this->addSql('
            UPDATE plan_sub_task pst
            SET 
                difficulty = pt.difficulty,
                requires_inspection = pt.requires_inspection
            FROM plan_task pt
            WHERE pst.task_template_id = pt.id
        ');

        // Remove defaults after data migration
        $this->addSql('ALTER TABLE plan_sub_task ALTER difficulty DROP DEFAULT');
        $this->addSql('ALTER TABLE plan_sub_task ALTER requires_inspection DROP DEFAULT');

        // Drop old columns from plan_task
        $this->addSql('ALTER TABLE plan_task DROP difficulty');
        $this->addSql('ALTER TABLE plan_task DROP requires_inspection');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE sub_task ALTER difficulty SET DEFAULT 3');
        $this->addSql('ALTER TABLE sub_task ALTER requires_inspection SET DEFAULT false');
        $this->addSql('ALTER TABLE sub_task ALTER inspected_at TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('COMMENT ON COLUMN sub_task.inspected_at IS NULL');
        $this->addSql('ALTER INDEX idx_75e844e4475ea6be RENAME TO idx_sub_task_inspected_by');
        $this->addSql('ALTER TABLE plan_sub_task DROP difficulty');
        $this->addSql('ALTER TABLE plan_sub_task DROP requires_inspection');
        $this->addSql('ALTER TABLE contribution ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE contribution ALTER created_at TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('COMMENT ON COLUMN contribution.created_at IS NULL');
        $this->addSql('ALTER INDEX idx_ea351e151fb354cd RENAME TO idx_ea351e15membership_id');
        $this->addSql('ALTER INDEX idx_ea351e15f26e5d72 RENAME TO idx_ea351e15sub_task_id');
        $this->addSql('ALTER TABLE plan_task ADD difficulty SMALLINT NOT NULL');
        $this->addSql('ALTER TABLE plan_task ADD requires_inspection BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE membership ALTER is_pilote SET DEFAULT false');
    }
}
