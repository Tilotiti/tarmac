<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251029112734 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add createdBy and createdAt fields to SubTask';
    }

    public function up(Schema $schema): void
    {
        // Add created_by_id (nullable)
        $this->addSql('ALTER TABLE sub_task ADD created_by_id INT DEFAULT NULL');
        
        // Add created_at as nullable first
        $this->addSql('ALTER TABLE sub_task ADD created_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN sub_task.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        
        // Set created_at to current timestamp for existing records
        $this->addSql('UPDATE sub_task SET created_at = NOW() WHERE created_at IS NULL');
        
        // Make created_at NOT NULL now that all records have a value
        $this->addSql('ALTER TABLE sub_task ALTER COLUMN created_at SET NOT NULL');
        
        // Add foreign key constraint and index
        $this->addSql('ALTER TABLE sub_task ADD CONSTRAINT FK_75E844E4B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_75E844E4B03A8386 ON sub_task (created_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE sub_task DROP CONSTRAINT FK_75E844E4B03A8386');
        $this->addSql('DROP INDEX IDX_75E844E4B03A8386');
        $this->addSql('ALTER TABLE sub_task DROP created_by_id');
        $this->addSql('ALTER TABLE sub_task DROP created_at');
    }
}
