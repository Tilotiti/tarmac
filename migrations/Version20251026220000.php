<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Refactor TaskActivityType enum values to remove TASK_/SUBTASK_ prefixes
 * The distinction between task and subtask activities is now handled by the subTask relationship
 */
final class Version20251026220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Refactor TaskActivityType enum values to remove TASK_/SUBTASK_ prefixes';
    }

    public function up(Schema $schema): void
    {
        // Update all task activity types to use generic values
        // TASK_DONE and SUBTASK_DONE both become DONE
        $this->addSql("UPDATE task_activity SET type = 'done' WHERE type IN ('task_done', 'subtask_done')");

        // TASK_UNDONE and SUBTASK_UNDONE both become UNDONE
        $this->addSql("UPDATE task_activity SET type = 'undone' WHERE type IN ('task_undone', 'subtask_undone')");

        // TASK_CLOSED becomes CLOSED
        $this->addSql("UPDATE task_activity SET type = 'closed' WHERE type = 'task_closed'");

        // TASK_CANCELLED and SUBTASK_CANCELLED both become CANCELLED
        $this->addSql("UPDATE task_activity SET type = 'cancelled' WHERE type IN ('task_cancelled', 'subtask_cancelled')");

        // SUBTASK_INSPECTED_APPROVED becomes INSPECTED_APPROVED
        $this->addSql("UPDATE task_activity SET type = 'inspected_approved' WHERE type = 'subtask_inspected_approved'");

        // SUBTASK_INSPECTED_REJECTED becomes INSPECTED_REJECTED
        $this->addSql("UPDATE task_activity SET type = 'inspected_rejected' WHERE type = 'subtask_inspected_rejected'");
    }

    public function down(Schema $schema): void
    {
        // Reverting is more complex because we need to check the subTask relationship
        // to determine if it should be TASK_ or SUBTASK_ prefix

        // Revert DONE based on whether it has a subtask
        $this->addSql("UPDATE task_activity SET type = 'task_done' WHERE type = 'done' AND sub_task_id IS NULL");
        $this->addSql("UPDATE task_activity SET type = 'subtask_done' WHERE type = 'done' AND sub_task_id IS NOT NULL");

        // Revert UNDONE based on whether it has a subtask
        $this->addSql("UPDATE task_activity SET type = 'task_undone' WHERE type = 'undone' AND sub_task_id IS NULL");
        $this->addSql("UPDATE task_activity SET type = 'subtask_undone' WHERE type = 'undone' AND sub_task_id IS NOT NULL");

        // Revert CLOSED (always task-level)
        $this->addSql("UPDATE task_activity SET type = 'task_closed' WHERE type = 'closed'");

        // Revert CANCELLED based on whether it has a subtask
        $this->addSql("UPDATE task_activity SET type = 'task_cancelled' WHERE type = 'cancelled' AND sub_task_id IS NULL");
        $this->addSql("UPDATE task_activity SET type = 'subtask_cancelled' WHERE type = 'cancelled' AND sub_task_id IS NOT NULL");

        // Revert INSPECTED_APPROVED (always task-level, was incorrectly named subtask before)
        $this->addSql("UPDATE task_activity SET type = 'subtask_inspected_approved' WHERE type = 'inspected_approved'");

        // Revert INSPECTED_REJECTED (always task-level, was incorrectly named subtask before)
        $this->addSql("UPDATE task_activity SET type = 'subtask_inspected_rejected' WHERE type = 'inspected_rejected'");
    }
}

