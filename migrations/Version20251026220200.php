<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add CREATED activity type to the ActivityType enum
 */
final class Version20251026220200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add CREATED activity type to the ActivityType enum';
    }

    public function up(Schema $schema): void
    {
        // No schema changes needed - PHP enum handles this
        // This migration serves as documentation that the enum was extended
    }

    public function down(Schema $schema): void
    {
        // Remove any 'created' activities if rolling back
        $this->addSql("DELETE FROM activity WHERE type = 'created'");
    }
}

