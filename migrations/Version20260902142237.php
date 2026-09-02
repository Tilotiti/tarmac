<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260902142237 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute club.health_check_dismissed_at pour le masquage de la checklist « Votre Tarmac est-il prêt ? ».';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE club ADD health_check_dismissed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE club DROP health_check_dismissed_at');
    }
}
