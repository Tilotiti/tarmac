<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add planPosition column to Task and SubTask to preserve maintenance plan ordering
 * Also backfills existing tasks/subtasks from maintenance plans
 */
final class Version20260105161711 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add planPosition column to Task and SubTask to preserve maintenance plan ordering';
    }

    public function up(Schema $schema): void
    {
        // Add columns
        $this->addSql('ALTER TABLE sub_task ADD plan_position SMALLINT DEFAULT NULL');
        $this->addSql('ALTER TABLE task ADD plan_position SMALLINT DEFAULT NULL');

        // Backfill task.plan_position from plan_task.position
        // Match tasks to plan_tasks by: plan_application -> plan -> plan_task (by title)
        $this->addSql('
            UPDATE task t
            SET plan_position = (
                SELECT pt.position
                FROM plan_application pa
                JOIN plan p ON pa.plan_id = p.id
                JOIN plan_task pt ON pt.plan_id = p.id AND pt.title = t.title
                WHERE t.plan_application_id = pa.id
                LIMIT 1
            )
            WHERE t.plan_application_id IS NOT NULL
        ');

        // Backfill sub_task.plan_position from plan_sub_task.position
        // Match subtasks to plan_sub_tasks by: task -> plan_application -> plan -> plan_task -> plan_sub_task (by title)
        $this->addSql('
            UPDATE sub_task st
            SET plan_position = (
                SELECT pst.position
                FROM task t
                JOIN plan_application pa ON t.plan_application_id = pa.id
                JOIN plan p ON pa.plan_id = p.id
                JOIN plan_task pt ON pt.plan_id = p.id AND pt.title = t.title
                JOIN plan_sub_task pst ON pst.task_template_id = pt.id AND pst.title = st.title
                WHERE st.task_id = t.id
                LIMIT 1
            )
            WHERE EXISTS (
                SELECT 1 FROM task t2 
                WHERE t2.id = st.task_id 
                AND t2.plan_application_id IS NOT NULL
            )
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE sub_task DROP plan_position');
        $this->addSql('ALTER TABLE task DROP plan_position');
    }
}
