<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728120832 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalisation des positions de sous-tâches : position devient la seule source de vérité de l\'ordre';
    }

    public function up(Schema $schema): void
    {
        // Jusqu'ici les sous-tâches étaient triées par (plan_position, position), plan_position
        // primant. Désormais seul position fait foi, ce qui rend la réorganisation manuelle
        // possible. On renumérote densément (0..n-1) chaque tâche en préservant l'ordre affiché
        // avant migration, de sorte que le changement soit invisible pour les clubs.
        $this->addSql(<<<'SQL'
            UPDATE sub_task AS s
            SET position = r.rn
            FROM (
                SELECT
                    id,
                    ROW_NUMBER() OVER (
                        PARTITION BY task_id
                        ORDER BY plan_position ASC NULLS LAST, position ASC, id ASC
                    ) - 1 AS rn
                FROM sub_task
            ) AS r
            WHERE s.id = r.id
              AND s.position <> r.rn
            SQL);
    }

    public function down(Schema $schema): void
    {
        // Migration de données uniquement : les positions d'origine (creuses ou dupliquées)
        // ne sont pas conservées, il n'y a donc rien à restaurer.
        $this->throwIrreversibleMigrationException(
            'La normalisation des positions de sous-tâches n\'est pas réversible.'
        );
    }
}
