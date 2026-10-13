<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013105914 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Suppression de la liaison anomaly_task, remplacée par anomaly_sub_task';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE anomaly_task DROP CONSTRAINT fk_f243e4a6baf977bb');
        $this->addSql('ALTER TABLE anomaly_task DROP CONSTRAINT fk_f243e4a68db60186');
        $this->addSql('DROP TABLE anomaly_task');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE anomaly_task (anomaly_id INT NOT NULL, task_id INT NOT NULL, PRIMARY KEY (anomaly_id, task_id))');
        $this->addSql('CREATE INDEX idx_f243e4a68db60186 ON anomaly_task (task_id)');
        $this->addSql('CREATE INDEX idx_f243e4a6baf977bb ON anomaly_task (anomaly_id)');
        $this->addSql('ALTER TABLE anomaly_task ADD CONSTRAINT fk_f243e4a6baf977bb FOREIGN KEY (anomaly_id) REFERENCES anomaly (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE anomaly_task ADD CONSTRAINT fk_f243e4a68db60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // Chaque sous-tâche liée redonne un lien vers sa tâche
        $this->addSql(<<<'SQL'
            INSERT INTO anomaly_task (anomaly_id, task_id)
            SELECT DISTINCT anomaly_sub_task.anomaly_id, sub_task.task_id
            FROM anomaly_sub_task
            JOIN sub_task ON sub_task.id = anomaly_sub_task.sub_task_id
            SQL);
    }
}
