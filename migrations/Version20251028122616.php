<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251028122616 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix contribution table ID column to use auto-increment sequence';
    }

    public function up(Schema $schema): void
    {
        // Set the contribution table ID column to use the auto-increment sequence
        // This fixes the "null value in column id violates not-null constraint" error
        $this->addSql('ALTER TABLE contribution ALTER id SET DEFAULT nextval(\'contribution_id_seq\')');
    }

    public function down(Schema $schema): void
    {
        // Revert to no default (original state)
        $this->addSql('ALTER TABLE contribution ALTER id DROP DEFAULT');
    }
}
