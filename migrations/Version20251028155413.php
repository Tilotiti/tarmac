<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251028155413 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add completedBy field to SubTask entity to track who submitted the completion form';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contribution ALTER created_at TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('COMMENT ON COLUMN contribution.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('ALTER INDEX idx_ea351e15sub_task_id RENAME TO IDX_EA351E15F26E5D72');
        $this->addSql('ALTER INDEX idx_ea351e15membership_id RENAME TO IDX_EA351E151FB354CD');
        $this->addSql('ALTER TABLE membership ALTER is_pilote DROP DEFAULT');
        $this->addSql('ALTER TABLE sub_task ADD completed_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sub_task ALTER requires_inspection DROP DEFAULT');
        $this->addSql('ALTER TABLE sub_task ALTER inspected_at TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('ALTER TABLE sub_task ALTER difficulty DROP DEFAULT');
        $this->addSql('COMMENT ON COLUMN sub_task.inspected_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('ALTER TABLE sub_task ADD CONSTRAINT FK_75E844E485ECDE76 FOREIGN KEY (completed_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_75E844E485ECDE76 ON sub_task (completed_by_id)');
        $this->addSql('ALTER INDEX idx_sub_task_inspected_by RENAME TO IDX_75E844E4475EA6BE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE contribution ALTER created_at TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('COMMENT ON COLUMN contribution.created_at IS NULL');
        $this->addSql('ALTER INDEX idx_ea351e151fb354cd RENAME TO idx_ea351e15membership_id');
        $this->addSql('ALTER INDEX idx_ea351e15f26e5d72 RENAME TO idx_ea351e15sub_task_id');
        $this->addSql('ALTER TABLE membership ALTER is_pilote SET DEFAULT false');
        $this->addSql('ALTER TABLE sub_task DROP CONSTRAINT FK_75E844E485ECDE76');
        $this->addSql('DROP INDEX IDX_75E844E485ECDE76');
        $this->addSql('ALTER TABLE sub_task DROP completed_by_id');
        $this->addSql('ALTER TABLE sub_task ALTER difficulty SET DEFAULT 3');
        $this->addSql('ALTER TABLE sub_task ALTER requires_inspection SET DEFAULT false');
        $this->addSql('ALTER TABLE sub_task ALTER inspected_at TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('COMMENT ON COLUMN sub_task.inspected_at IS NULL');
        $this->addSql('ALTER INDEX idx_75e844e4475ea6be RENAME TO idx_sub_task_inspected_by');
    }
}
