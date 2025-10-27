<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251026165501 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER INDEX idx_ecb4e3168db60186 RENAME TO IDX_AC74095A8DB60186');
        $this->addSql('ALTER INDEX idx_ecb4e316f26e5d72 RENAME TO IDX_AC74095AF26E5D72');
        $this->addSql('ALTER INDEX idx_ecb4e316a76ed395 RENAME TO IDX_AC74095AA76ED395');
        $this->addSql('ALTER TABLE plan_task DROP relative_due_days');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER INDEX idx_ac74095a8db60186 RENAME TO idx_ecb4e3168db60186');
        $this->addSql('ALTER INDEX idx_ac74095aa76ed395 RENAME TO idx_ecb4e316a76ed395');
        $this->addSql('ALTER INDEX idx_ac74095af26e5d72 RENAME TO idx_ecb4e316f26e5d72');
        $this->addSql('ALTER TABLE plan_task ADD relative_due_days INT DEFAULT NULL');
    }
}
