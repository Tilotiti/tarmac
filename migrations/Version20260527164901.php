<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute la traçabilité du mot du gestionnaire :
 * - quand le message d'accueil a été mis à jour
 * - par qui (FK user, ON DELETE SET NULL pour ne pas perdre le message si l'auteur est supprimé)
 */
final class Version20260527164901 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add welcome_message_updated_at and welcome_message_updated_by_id to club';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE club ADD welcome_message_updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE club ADD welcome_message_updated_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE club ADD CONSTRAINT FK_B8EE38722E439AA6 FOREIGN KEY (welcome_message_updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_B8EE38722E439AA6 ON club (welcome_message_updated_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE club DROP CONSTRAINT FK_B8EE38722E439AA6');
        $this->addSql('DROP INDEX IDX_B8EE38722E439AA6');
        $this->addSql('ALTER TABLE club DROP welcome_message_updated_at');
        $this->addSql('ALTER TABLE club DROP welcome_message_updated_by_id');
    }
}
