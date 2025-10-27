<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251027083248 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE task_activity_id_seq CASCADE');
        $this->addSql('CREATE TABLE activity (id SERIAL NOT NULL, task_id INT NOT NULL, sub_task_id INT DEFAULT NULL, user_id INT NOT NULL, type VARCHAR(30) NOT NULL, message TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_AC74095A8DB60186 ON activity (task_id)');
        $this->addSql('CREATE INDEX IDX_AC74095AF26E5D72 ON activity (sub_task_id)');
        $this->addSql('CREATE INDEX IDX_AC74095AA76ED395 ON activity (user_id)');
        $this->addSql('CREATE INDEX idx_activity_created_at ON activity (created_at)');
        $this->addSql('CREATE INDEX idx_activity_type ON activity (type)');
        $this->addSql('COMMENT ON COLUMN activity.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A8DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095AF26E5D72 FOREIGN KEY (sub_task_id) REFERENCES sub_task (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095AA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task_activity DROP CONSTRAINT fk_ecb4e3168db60186');
        $this->addSql('ALTER TABLE task_activity DROP CONSTRAINT fk_ecb4e316a76ed395');
        $this->addSql('ALTER TABLE task_activity DROP CONSTRAINT fk_ecb4e316f26e5d72');
        $this->addSql('DROP TABLE task_activity');
        $this->addSql('ALTER TABLE plan_application ALTER due_at TYPE DATE');
        $this->addSql('COMMENT ON COLUMN plan_application.due_at IS \'(DC2Type:date_immutable)\'');
        $this->addSql('ALTER TABLE task ALTER due_at TYPE DATE');
        $this->addSql('COMMENT ON COLUMN task.due_at IS \'(DC2Type:date_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE SEQUENCE task_activity_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE task_activity (id SERIAL NOT NULL, task_id INT NOT NULL, sub_task_id INT DEFAULT NULL, user_id INT NOT NULL, type VARCHAR(30) NOT NULL, message TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_ac74095a8db60186 ON task_activity (task_id)');
        $this->addSql('CREATE INDEX idx_ac74095aa76ed395 ON task_activity (user_id)');
        $this->addSql('CREATE INDEX idx_ac74095af26e5d72 ON task_activity (sub_task_id)');
        $this->addSql('CREATE INDEX idx_activity_created_at ON task_activity (created_at)');
        $this->addSql('CREATE INDEX idx_activity_type ON task_activity (type)');
        $this->addSql('COMMENT ON COLUMN task_activity.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('ALTER TABLE task_activity ADD CONSTRAINT fk_ecb4e3168db60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task_activity ADD CONSTRAINT fk_ecb4e316a76ed395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task_activity ADD CONSTRAINT fk_ecb4e316f26e5d72 FOREIGN KEY (sub_task_id) REFERENCES sub_task (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE activity DROP CONSTRAINT FK_AC74095A8DB60186');
        $this->addSql('ALTER TABLE activity DROP CONSTRAINT FK_AC74095AF26E5D72');
        $this->addSql('ALTER TABLE activity DROP CONSTRAINT FK_AC74095AA76ED395');
        $this->addSql('DROP TABLE activity');
        $this->addSql('ALTER TABLE plan_application ALTER due_at TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('COMMENT ON COLUMN plan_application.due_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('ALTER TABLE task ALTER due_at TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('COMMENT ON COLUMN task.due_at IS \'(DC2Type:datetimetz_immutable)\'');
    }
}
