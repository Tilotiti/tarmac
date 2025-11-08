<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251108114441 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER INDEX idx_activity_purchase RENAME TO IDX_AC74095A558FBEB9');
        $this->addSql('ALTER TABLE purchase ALTER quantity DROP DEFAULT');
        $this->addSql('ALTER INDEX idx_purchase_club RENAME TO IDX_6117D13B61190A32');
        $this->addSql('ALTER INDEX idx_purchase_created_by RENAME TO IDX_6117D13BB03A8386');
        $this->addSql('ALTER INDEX idx_purchase_purchased_by RENAME TO IDX_6117D13B51D43F65');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE purchase ALTER quantity SET DEFAULT 1');
        $this->addSql('ALTER INDEX idx_6117d13b61190a32 RENAME TO idx_purchase_club');
        $this->addSql('ALTER INDEX idx_6117d13bb03a8386 RENAME TO idx_purchase_created_by');
        $this->addSql('ALTER INDEX idx_6117d13b51d43f65 RENAME TO idx_purchase_purchased_by');
        $this->addSql('ALTER INDEX idx_ac74095a558fbeb9 RENAME TO idx_activity_purchase');
    }
}
