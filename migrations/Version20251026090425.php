<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251026090425 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE plan (id SERIAL NOT NULL, club_id INT NOT NULL, created_by_id INT DEFAULT NULL, name VARCHAR(180) NOT NULL, description TEXT DEFAULT NULL, equipment_type VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_DD5A5B7D61190A32 ON plan (club_id)');
        $this->addSql('CREATE INDEX IDX_DD5A5B7DB03A8386 ON plan (created_by_id)');
        $this->addSql('COMMENT ON COLUMN plan.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN plan.updated_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('CREATE TABLE plan_application (id SERIAL NOT NULL, plan_id INT NOT NULL, equipment_id INT NOT NULL, applied_by_id INT NOT NULL, cancelled_by_id INT DEFAULT NULL, applied_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, due_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, cancelled_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_50E205AAE899029B ON plan_application (plan_id)');
        $this->addSql('CREATE INDEX IDX_50E205AA517FE9FE ON plan_application (equipment_id)');
        $this->addSql('CREATE INDEX IDX_50E205AA4B8DEE4D ON plan_application (applied_by_id)');
        $this->addSql('CREATE INDEX IDX_50E205AA187B2D12 ON plan_application (cancelled_by_id)');
        $this->addSql('CREATE INDEX idx_application_applied_at ON plan_application (applied_at)');
        $this->addSql('CREATE INDEX idx_application_due_at ON plan_application (due_at)');
        $this->addSql('COMMENT ON COLUMN plan_application.applied_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN plan_application.due_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN plan_application.cancelled_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('CREATE TABLE plan_sub_task (id SERIAL NOT NULL, task_template_id INT NOT NULL, title VARCHAR(180) NOT NULL, description TEXT DEFAULT NULL, difficulty SMALLINT NOT NULL, relative_due_days INT DEFAULT NULL, position SMALLINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_7B8D739E43AFA28A ON plan_sub_task (task_template_id)');
        $this->addSql('CREATE TABLE plan_task (id SERIAL NOT NULL, plan_id INT NOT NULL, title VARCHAR(180) NOT NULL, description TEXT DEFAULT NULL, difficulty SMALLINT NOT NULL, requires_inspection BOOLEAN NOT NULL, relative_due_days INT DEFAULT NULL, position SMALLINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_47A817D7E899029B ON plan_task (plan_id)');
        $this->addSql('CREATE TABLE sub_task (id SERIAL NOT NULL, task_id INT NOT NULL, claimed_by_id INT DEFAULT NULL, done_by_id INT DEFAULT NULL, inspected_by_id INT DEFAULT NULL, cancelled_by_id INT DEFAULT NULL, title VARCHAR(180) NOT NULL, description TEXT DEFAULT NULL, due_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, difficulty SMALLINT NOT NULL, status VARCHAR(20) NOT NULL, claimed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, done_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, inspected_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, cancelled_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, position SMALLINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_75E844E48DB60186 ON sub_task (task_id)');
        $this->addSql('CREATE INDEX IDX_75E844E4F67E7A38 ON sub_task (claimed_by_id)');
        $this->addSql('CREATE INDEX IDX_75E844E435AE3EF9 ON sub_task (done_by_id)');
        $this->addSql('CREATE INDEX IDX_75E844E4475EA6BE ON sub_task (inspected_by_id)');
        $this->addSql('CREATE INDEX IDX_75E844E4187B2D12 ON sub_task (cancelled_by_id)');
        $this->addSql('CREATE INDEX idx_subtask_status ON sub_task (status)');
        $this->addSql('CREATE INDEX idx_subtask_due_at ON sub_task (due_at)');
        $this->addSql('CREATE INDEX idx_subtask_difficulty ON sub_task (difficulty)');
        $this->addSql('CREATE INDEX idx_subtask_position ON sub_task (position)');
        $this->addSql('COMMENT ON COLUMN sub_task.due_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN sub_task.claimed_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN sub_task.done_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN sub_task.inspected_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN sub_task.cancelled_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('CREATE TABLE task (id SERIAL NOT NULL, club_id INT NOT NULL, equipment_id INT NOT NULL, claimed_by_id INT DEFAULT NULL, done_by_id INT DEFAULT NULL, inspected_by_id INT DEFAULT NULL, cancelled_by_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, plan_application_id INT DEFAULT NULL, title VARCHAR(180) NOT NULL, description TEXT DEFAULT NULL, due_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, difficulty SMALLINT NOT NULL, requires_inspection BOOLEAN NOT NULL, status VARCHAR(20) NOT NULL, claimed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, done_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, inspected_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, cancelled_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_527EDB2561190A32 ON task (club_id)');
        $this->addSql('CREATE INDEX IDX_527EDB25517FE9FE ON task (equipment_id)');
        $this->addSql('CREATE INDEX IDX_527EDB25F67E7A38 ON task (claimed_by_id)');
        $this->addSql('CREATE INDEX IDX_527EDB2535AE3EF9 ON task (done_by_id)');
        $this->addSql('CREATE INDEX IDX_527EDB25475EA6BE ON task (inspected_by_id)');
        $this->addSql('CREATE INDEX IDX_527EDB25187B2D12 ON task (cancelled_by_id)');
        $this->addSql('CREATE INDEX IDX_527EDB25B03A8386 ON task (created_by_id)');
        $this->addSql('CREATE INDEX IDX_527EDB2581DD6AC7 ON task (plan_application_id)');
        $this->addSql('CREATE INDEX idx_task_status ON task (status)');
        $this->addSql('CREATE INDEX idx_task_due_at ON task (due_at)');
        $this->addSql('CREATE INDEX idx_task_difficulty ON task (difficulty)');
        $this->addSql('CREATE INDEX idx_task_created_at ON task (created_at)');
        $this->addSql('COMMENT ON COLUMN task.due_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN task.claimed_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN task.done_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN task.inspected_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN task.cancelled_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN task.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('CREATE TABLE task_activity (id SERIAL NOT NULL, task_id INT NOT NULL, sub_task_id INT DEFAULT NULL, user_id INT NOT NULL, type VARCHAR(30) NOT NULL, message TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_ECB4E3168DB60186 ON task_activity (task_id)');
        $this->addSql('CREATE INDEX IDX_ECB4E316F26E5D72 ON task_activity (sub_task_id)');
        $this->addSql('CREATE INDEX IDX_ECB4E316A76ED395 ON task_activity (user_id)');
        $this->addSql('CREATE INDEX idx_activity_created_at ON task_activity (created_at)');
        $this->addSql('CREATE INDEX idx_activity_type ON task_activity (type)');
        $this->addSql('COMMENT ON COLUMN task_activity.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('ALTER TABLE plan ADD CONSTRAINT FK_DD5A5B7D61190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE plan ADD CONSTRAINT FK_DD5A5B7DB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE plan_application ADD CONSTRAINT FK_50E205AAE899029B FOREIGN KEY (plan_id) REFERENCES plan (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE plan_application ADD CONSTRAINT FK_50E205AA517FE9FE FOREIGN KEY (equipment_id) REFERENCES equipment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE plan_application ADD CONSTRAINT FK_50E205AA4B8DEE4D FOREIGN KEY (applied_by_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE plan_application ADD CONSTRAINT FK_50E205AA187B2D12 FOREIGN KEY (cancelled_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE plan_sub_task ADD CONSTRAINT FK_7B8D739E43AFA28A FOREIGN KEY (task_template_id) REFERENCES plan_task (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE plan_task ADD CONSTRAINT FK_47A817D7E899029B FOREIGN KEY (plan_id) REFERENCES plan (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sub_task ADD CONSTRAINT FK_75E844E48DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sub_task ADD CONSTRAINT FK_75E844E4F67E7A38 FOREIGN KEY (claimed_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sub_task ADD CONSTRAINT FK_75E844E435AE3EF9 FOREIGN KEY (done_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sub_task ADD CONSTRAINT FK_75E844E4475EA6BE FOREIGN KEY (inspected_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sub_task ADD CONSTRAINT FK_75E844E4187B2D12 FOREIGN KEY (cancelled_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB2561190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25517FE9FE FOREIGN KEY (equipment_id) REFERENCES equipment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25F67E7A38 FOREIGN KEY (claimed_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB2535AE3EF9 FOREIGN KEY (done_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25475EA6BE FOREIGN KEY (inspected_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25187B2D12 FOREIGN KEY (cancelled_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB2581DD6AC7 FOREIGN KEY (plan_application_id) REFERENCES plan_application (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task_activity ADD CONSTRAINT FK_ECB4E3168DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task_activity ADD CONSTRAINT FK_ECB4E316F26E5D72 FOREIGN KEY (sub_task_id) REFERENCES sub_task (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task_activity ADD CONSTRAINT FK_ECB4E316A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE plan DROP CONSTRAINT FK_DD5A5B7D61190A32');
        $this->addSql('ALTER TABLE plan DROP CONSTRAINT FK_DD5A5B7DB03A8386');
        $this->addSql('ALTER TABLE plan_application DROP CONSTRAINT FK_50E205AAE899029B');
        $this->addSql('ALTER TABLE plan_application DROP CONSTRAINT FK_50E205AA517FE9FE');
        $this->addSql('ALTER TABLE plan_application DROP CONSTRAINT FK_50E205AA4B8DEE4D');
        $this->addSql('ALTER TABLE plan_application DROP CONSTRAINT FK_50E205AA187B2D12');
        $this->addSql('ALTER TABLE plan_sub_task DROP CONSTRAINT FK_7B8D739E43AFA28A');
        $this->addSql('ALTER TABLE plan_task DROP CONSTRAINT FK_47A817D7E899029B');
        $this->addSql('ALTER TABLE sub_task DROP CONSTRAINT FK_75E844E48DB60186');
        $this->addSql('ALTER TABLE sub_task DROP CONSTRAINT FK_75E844E4F67E7A38');
        $this->addSql('ALTER TABLE sub_task DROP CONSTRAINT FK_75E844E435AE3EF9');
        $this->addSql('ALTER TABLE sub_task DROP CONSTRAINT FK_75E844E4475EA6BE');
        $this->addSql('ALTER TABLE sub_task DROP CONSTRAINT FK_75E844E4187B2D12');
        $this->addSql('ALTER TABLE task DROP CONSTRAINT FK_527EDB2561190A32');
        $this->addSql('ALTER TABLE task DROP CONSTRAINT FK_527EDB25517FE9FE');
        $this->addSql('ALTER TABLE task DROP CONSTRAINT FK_527EDB25F67E7A38');
        $this->addSql('ALTER TABLE task DROP CONSTRAINT FK_527EDB2535AE3EF9');
        $this->addSql('ALTER TABLE task DROP CONSTRAINT FK_527EDB25475EA6BE');
        $this->addSql('ALTER TABLE task DROP CONSTRAINT FK_527EDB25187B2D12');
        $this->addSql('ALTER TABLE task DROP CONSTRAINT FK_527EDB25B03A8386');
        $this->addSql('ALTER TABLE task DROP CONSTRAINT FK_527EDB2581DD6AC7');
        $this->addSql('ALTER TABLE task_activity DROP CONSTRAINT FK_ECB4E3168DB60186');
        $this->addSql('ALTER TABLE task_activity DROP CONSTRAINT FK_ECB4E316F26E5D72');
        $this->addSql('ALTER TABLE task_activity DROP CONSTRAINT FK_ECB4E316A76ED395');
        $this->addSql('DROP TABLE plan');
        $this->addSql('DROP TABLE plan_application');
        $this->addSql('DROP TABLE plan_sub_task');
        $this->addSql('DROP TABLE plan_task');
        $this->addSql('DROP TABLE sub_task');
        $this->addSql('DROP TABLE task');
        $this->addSql('DROP TABLE task_activity');
    }
}
