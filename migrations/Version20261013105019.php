<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013105019 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Liaison anomaly_sub_task et conversion des liens anomalie → tâche';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE anomaly_sub_task (anomaly_id INT NOT NULL, sub_task_id INT NOT NULL, PRIMARY KEY (anomaly_id, sub_task_id))');
        $this->addSql('CREATE INDEX IDX_190D14CABAF977BB ON anomaly_sub_task (anomaly_id)');
        $this->addSql('CREATE INDEX IDX_190D14CAF26E5D72 ON anomaly_sub_task (sub_task_id)');
        $this->addSql('ALTER TABLE anomaly_sub_task ADD CONSTRAINT FK_190D14CABAF977BB FOREIGN KEY (anomaly_id) REFERENCES anomaly (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE anomaly_sub_task ADD CONSTRAINT FK_190D14CAF26E5D72 FOREIGN KEY (sub_task_id) REFERENCES sub_task (id) ON DELETE CASCADE');

        // Chaque lien vers une tâche devient un lien vers ses sous-tâches encore actives.
        // Un lien vers une tâche annulée ne comptait déjà plus : on ne le recopie pas.
        $this->addSql(<<<'SQL'
            INSERT INTO anomaly_sub_task (anomaly_id, sub_task_id)
            SELECT DISTINCT anomaly_task.anomaly_id, sub_task.id
            FROM anomaly_task
            JOIN task ON task.id = anomaly_task.task_id
            JOIN sub_task ON sub_task.task_id = task.id
            WHERE task.status <> 'cancelled' AND sub_task.status <> 'cancelled'
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE anomaly_sub_task DROP CONSTRAINT FK_190D14CABAF977BB');
        $this->addSql('ALTER TABLE anomaly_sub_task DROP CONSTRAINT FK_190D14CAF26E5D72');
        $this->addSql('DROP TABLE anomaly_sub_task');
    }
}
