<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251108111954 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Add documentation columns to maintenance entities
        $this->addSql('ALTER TABLE plan_sub_task ADD documentation VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE plan_task ADD documentation VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE sub_task ADD documentation VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE task ADD documentation VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Remove documentation columns
        $this->addSql('ALTER TABLE task DROP documentation');
        $this->addSql('ALTER TABLE sub_task DROP documentation');
        $this->addSql('ALTER TABLE plan_task DROP documentation');
        $this->addSql('ALTER TABLE plan_sub_task DROP documentation');
    }
}
