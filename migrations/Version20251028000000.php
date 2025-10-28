<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Task System Upgrade Migration
 * - Move inspection and difficulty to subtask level
 * - Add isPilote to membership
 * - Create contribution table
 * - Add 'done' status
 * - Migrate existing data
 */
final class Version20251028000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Task system upgrade: move inspection and difficulty to subtask, add contributions, add isPilote, add done status';
    }

    public function up(Schema $schema): void
    {
        // Add is_pilote to membership table
        $this->addSql('ALTER TABLE membership ADD is_pilote BOOLEAN DEFAULT FALSE NOT NULL');

        // Add inspection fields to sub_task table
        $this->addSql('ALTER TABLE sub_task ADD requires_inspection BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('ALTER TABLE sub_task ADD inspected_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sub_task ADD inspected_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE sub_task ADD CONSTRAINT FK_sub_task_inspected_by FOREIGN KEY (inspected_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_sub_task_inspected_by ON sub_task (inspected_by_id)');

        // Add difficulty to sub_task table
        $this->addSql('ALTER TABLE sub_task ADD difficulty SMALLINT DEFAULT 3 NOT NULL');

        // Update status constraints to include 'done' value
        $this->addSql('ALTER TABLE task DROP CONSTRAINT IF EXISTS task_status_check');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT task_status_check CHECK (status IN (\'open\', \'done\', \'closed\', \'cancelled\'))');

        $this->addSql('ALTER TABLE sub_task DROP CONSTRAINT IF EXISTS subtask_status_check');
        $this->addSql('ALTER TABLE sub_task ADD CONSTRAINT subtask_status_check CHECK (status IN (\'open\', \'done\', \'closed\', \'cancelled\'))');

        // Create contribution table
        $this->addSql('CREATE SEQUENCE contribution_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE contribution (
            id INT NOT NULL, 
            sub_task_id INT NOT NULL, 
            membership_id INT NOT NULL, 
            time_spent INT NOT NULL, 
            created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, 
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_EA351E15sub_task_id ON contribution (sub_task_id)');
        $this->addSql('CREATE INDEX IDX_EA351E15membership_id ON contribution (membership_id)');
        $this->addSql('CREATE INDEX idx_contribution_created_at ON contribution (created_at)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CONTRIBUTION ON contribution (sub_task_id, membership_id)');
        $this->addSql('ALTER TABLE contribution ADD CONSTRAINT FK_EA351E15sub_task FOREIGN KEY (sub_task_id) REFERENCES sub_task (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE contribution ADD CONSTRAINT FK_EA351E15membership FOREIGN KEY (membership_id) REFERENCES membership (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // Data migration: Create subtasks for tasks without any
        $this->addSql("
            INSERT INTO sub_task (task_id, title, description, status, difficulty, requires_inspection, position, done_by_id, done_at, cancelled_by_id, cancelled_at)
            SELECT 
                t.id,
                t.title,
                t.description,
                CASE 
                    WHEN t.status = 'closed' THEN 'closed'
                    WHEN t.status = 'cancelled' THEN 'cancelled'
                    ELSE 'open'
                END,
                t.difficulty,
                t.requires_inspection,
                1,
                t.done_by_id,
                t.done_at,
                t.cancelled_by_id,
                t.cancelled_at
            FROM task t
            WHERE NOT EXISTS (
                SELECT 1 FROM sub_task st WHERE st.task_id = t.id
            )
        ");

        // Data migration: Set difficulty on existing subtasks from parent task
        $this->addSql("
            UPDATE sub_task st
            SET difficulty = t.difficulty
            FROM task t
            WHERE st.task_id = t.id
        ");

        // Data migration: Move inspection fields from task to all its subtasks
        $this->addSql("
            UPDATE sub_task st
            SET 
                requires_inspection = t.requires_inspection,
                inspected_by_id = t.inspected_by_id,
                inspected_at = t.inspected_at
            FROM task t
            WHERE st.task_id = t.id
        ");

        // Data migration: Create contributions for existing subtasks with doneBy
        $this->addSql("
            INSERT INTO contribution (id, sub_task_id, membership_id, time_spent, created_at)
            SELECT 
                nextval('contribution_id_seq'),
                st.id,
                m.id,
                1,
                COALESCE(st.done_at, NOW())
            FROM sub_task st
            JOIN task t ON st.task_id = t.id
            JOIN membership m ON m.user_id = st.done_by_id AND m.club_id = t.club_id
            WHERE st.done_by_id IS NOT NULL
            ON CONFLICT (sub_task_id, membership_id) DO NOTHING
        ");

        // Drop old columns from task table
        $this->addSql('ALTER TABLE task DROP CONSTRAINT IF EXISTS FK_task_done_by');
        $this->addSql('ALTER TABLE task DROP CONSTRAINT IF EXISTS FK_task_inspected_by');
        $this->addSql('DROP INDEX IF EXISTS IDX_task_done_by');
        $this->addSql('DROP INDEX IF EXISTS IDX_task_inspected_by');
        $this->addSql('DROP INDEX IF EXISTS idx_task_difficulty');
        $this->addSql('ALTER TABLE task DROP COLUMN IF EXISTS done_by_id');
        $this->addSql('ALTER TABLE task DROP COLUMN IF EXISTS done_at');
        $this->addSql('ALTER TABLE task DROP COLUMN IF EXISTS inspected_by_id');
        $this->addSql('ALTER TABLE task DROP COLUMN IF EXISTS inspected_at');
        $this->addSql('ALTER TABLE task DROP COLUMN IF EXISTS requires_inspection');
        $this->addSql('ALTER TABLE task DROP COLUMN IF EXISTS difficulty');
    }

    public function down(Schema $schema): void
    {
        // This is a complex migration - rollback would be very complex and potentially data-losing
        // It's recommended to use database backups instead of rolling back this migration
        $this->throwIrreversibleMigrationException('This migration cannot be safely reversed. Please restore from backup if needed.');
    }
}

