<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728130607 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Suppression de sub_task.plan_position, devenue une colonne écrite mais jamais lue';
    }

    public function up(Schema $schema): void
    {
        // Depuis que l'ordre des sous-tâches est réorganisable manuellement, `position`
        // fait seule autorité. `plan_position` n'était plus lue nulle part : elle était
        // uniquement renseignée à l'application d'un plan, et devenait fausse dès la
        // première réorganisation. La ressemblance avec une colonne d'ordre a d'ailleurs
        // produit trois ORDER BY erronés (corrigés en 490b46a) — on la retire.
        // La provenance d'une sous-tâche reste traçable par task -> plan_application -> plan.
        $this->addSql('ALTER TABLE sub_task DROP plan_position');
    }

    public function down(Schema $schema): void
    {
        // La colonne est restaurée vide : les valeurs d'origine ne sont pas conservées.
        // Elles restent reconstituables depuis les templates du plan appliqué.
        $this->addSql('ALTER TABLE sub_task ADD plan_position SMALLINT DEFAULT NULL');
    }
}
