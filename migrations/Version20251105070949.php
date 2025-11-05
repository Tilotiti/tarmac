<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251105070949 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create purchase table and update activity table for purchase support';
    }

    public function up(Schema $schema): void
    {
        // Create purchase table
        $this->addSql('CREATE TABLE purchase (
            id SERIAL NOT NULL,
            club_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            description TEXT DEFAULT NULL,
            status VARCHAR(255) NOT NULL,
            request_image VARCHAR(255) DEFAULT NULL,
            bill_image VARCHAR(255) DEFAULT NULL,
            created_by_id INT NOT NULL,
            created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
            purchased_by_id INT DEFAULT NULL,
            purchased_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
            delivered_by_id INT DEFAULT NULL,
            delivered_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
            expected_delivery_date DATE DEFAULT NULL,
            reimbursed_by_id INT DEFAULT NULL,
            reimbursed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
            cancelled_by_id INT DEFAULT NULL,
            cancelled_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
            approved_by_id INT DEFAULT NULL,
            approved_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX idx_purchase_status ON purchase (status)');
        $this->addSql('CREATE INDEX idx_purchase_created_at ON purchase (created_at)');
        $this->addSql('CREATE INDEX IDX_PURCHASE_CLUB ON purchase (club_id)');
        $this->addSql('CREATE INDEX IDX_PURCHASE_CREATED_BY ON purchase (created_by_id)');
        $this->addSql('CREATE INDEX IDX_PURCHASE_PURCHASED_BY ON purchase (purchased_by_id)');
        $this->addSql('COMMENT ON COLUMN purchase.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN purchase.purchased_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN purchase.delivered_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN purchase.expected_delivery_date IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN purchase.reimbursed_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN purchase.cancelled_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN purchase.approved_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('ALTER TABLE purchase ADD CONSTRAINT FK_PURCHASE_CLUB FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE purchase ADD CONSTRAINT FK_PURCHASE_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE purchase ADD CONSTRAINT FK_PURCHASE_PURCHASED_BY FOREIGN KEY (purchased_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE purchase ADD CONSTRAINT FK_PURCHASE_DELIVERED_BY FOREIGN KEY (delivered_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE purchase ADD CONSTRAINT FK_PURCHASE_REIMBURSED_BY FOREIGN KEY (reimbursed_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE purchase ADD CONSTRAINT FK_PURCHASE_CANCELLED_BY FOREIGN KEY (cancelled_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE purchase ADD CONSTRAINT FK_PURCHASE_APPROVED_BY FOREIGN KEY (approved_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        // Update activity table to make task nullable and add purchase relationship
        $this->addSql('ALTER TABLE activity ALTER COLUMN task_id DROP NOT NULL');
        $this->addSql('ALTER TABLE activity ADD purchase_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_ACTIVITY_PURCHASE ON activity (purchase_id)');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_ACTIVITY_PURCHASE FOREIGN KEY (purchase_id) REFERENCES purchase (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // Remove activity purchase relationship
        $this->addSql('ALTER TABLE activity DROP CONSTRAINT FK_ACTIVITY_PURCHASE');
        $this->addSql('DROP INDEX IDX_ACTIVITY_PURCHASE');
        $this->addSql('ALTER TABLE activity DROP purchase_id');
        $this->addSql('ALTER TABLE activity ALTER COLUMN task_id SET NOT NULL');

        // Drop purchase table
        $this->addSql('ALTER TABLE purchase DROP CONSTRAINT FK_PURCHASE_CLUB');
        $this->addSql('ALTER TABLE purchase DROP CONSTRAINT FK_PURCHASE_CREATED_BY');
        $this->addSql('ALTER TABLE purchase DROP CONSTRAINT FK_PURCHASE_PURCHASED_BY');
        $this->addSql('ALTER TABLE purchase DROP CONSTRAINT FK_PURCHASE_DELIVERED_BY');
        $this->addSql('ALTER TABLE purchase DROP CONSTRAINT FK_PURCHASE_REIMBURSED_BY');
        $this->addSql('ALTER TABLE purchase DROP CONSTRAINT FK_PURCHASE_CANCELLED_BY');
        $this->addSql('ALTER TABLE purchase DROP CONSTRAINT FK_PURCHASE_APPROVED_BY');
        $this->addSql('DROP TABLE purchase');
    }
}
