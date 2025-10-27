<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rename task_activity table to activity
 */
final class Version20251026220100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename task_activity table to activity';
    }

    public function up(Schema $schema): void
    {
        // Rename the table
        $this->addSql('ALTER TABLE task_activity RENAME TO activity');

        // Update the sequence name if it exists
        $this->addSql('ALTER SEQUENCE IF EXISTS task_activity_id_seq RENAME TO activity_id_seq');
    }

    public function down(Schema $schema): void
    {
        // Revert table name
        $this->addSql('ALTER TABLE activity RENAME TO task_activity');

        // Revert sequence name
        $this->addSql('ALTER SEQUENCE IF EXISTS activity_id_seq RENAME TO task_activity_id_seq');
    }
}

