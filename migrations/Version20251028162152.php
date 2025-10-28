<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251028162152 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change Contribution time_spent from INTEGER to DECIMAL(10,2) to support fractional hours';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contribution ALTER time_spent TYPE NUMERIC(10, 2)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE contribution ALTER time_spent TYPE INT');
    }
}
