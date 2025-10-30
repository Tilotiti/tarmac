<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migrate difficulty scale from 5 levels to 3 levels
 * 
 * Old scale: 1=Débutant, 2=Facile, 3=Moyen, 4=Difficile, 5=Expert
 * New scale: 1=Débutant, 2=Expérimenté, 3=Expert
 * 
 * Mapping:
 * - Old 1 (Débutant) → New 1 (Débutant)
 * - Old 2 (Facile) → New 1 (Débutant)
 * - Old 3 (Moyen) → New 2 (Expérimenté)
 * - Old 4 (Difficile) → New 3 (Expert)
 * - Old 5 (Expert) → New 3 (Expert)
 */
final class Version20251030115626 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate difficulty scale from 5 levels to 3 levels (1=Débutant, 2=Expérimenté, 3=Expert)';
    }

    public function up(Schema $schema): void
    {
        // Migrate SubTask difficulties
        // Old 2 (Facile) → New 1 (Débutant)
        $this->addSql("UPDATE sub_task SET difficulty = 1 WHERE difficulty = 2");

        // Old 3 (Moyen) → New 2 (Expérimenté)
        $this->addSql("UPDATE sub_task SET difficulty = 2 WHERE difficulty = 3");

        // Old 4 (Difficile) → New 3 (Expert)
        $this->addSql("UPDATE sub_task SET difficulty = 3 WHERE difficulty = 4");

        // Old 5 (Expert) → New 3 (Expert)
        $this->addSql("UPDATE sub_task SET difficulty = 3 WHERE difficulty = 5");

        // Migrate PlanSubTask difficulties
        // Old 2 (Facile) → New 1 (Débutant)
        $this->addSql("UPDATE plan_sub_task SET difficulty = 1 WHERE difficulty = 2");

        // Old 3 (Moyen) → New 2 (Expérimenté)
        $this->addSql("UPDATE plan_sub_task SET difficulty = 2 WHERE difficulty = 3");

        // Old 4 (Difficile) → New 3 (Expert)
        $this->addSql("UPDATE plan_sub_task SET difficulty = 3 WHERE difficulty = 4");

        // Old 5 (Expert) → New 3 (Expert)
        $this->addSql("UPDATE plan_sub_task SET difficulty = 3 WHERE difficulty = 5");
    }

    public function down(Schema $schema): void
    {
        // Reverse the migration by converting back to 5-level scale
        // This is approximate as we can't perfectly map 3 levels back to 5

        // Migrate SubTask difficulties back
        // New 1 (Débutant) → Old 1 (Débutant) [keep as is]
        // New 2 (Expérimenté) → Old 3 (Moyen)
        $this->addSql("UPDATE sub_task SET difficulty = 3 WHERE difficulty = 2");

        // New 3 (Expert) → Old 5 (Expert)
        $this->addSql("UPDATE sub_task SET difficulty = 5 WHERE difficulty = 3");

        // Migrate PlanSubTask difficulties back
        // New 1 (Débutant) → Old 1 (Débutant) [keep as is]
        // New 2 (Expérimenté) → Old 3 (Moyen)
        $this->addSql("UPDATE plan_sub_task SET difficulty = 3 WHERE difficulty = 2");

        // New 3 (Expert) → Old 5 (Expert)
        $this->addSql("UPDATE plan_sub_task SET difficulty = 5 WHERE difficulty = 3");
    }
}
