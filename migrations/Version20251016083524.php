<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251016083524 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE club (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, subdomain VARCHAR(100) NOT NULL, description TEXT DEFAULT NULL, active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CLUB_SUBDOMAIN ON club (subdomain)');
        $this->addSql('COMMENT ON COLUMN club.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('CREATE TABLE user_club (id SERIAL NOT NULL, user_id INT NOT NULL, club_id INT NOT NULL, is_manager BOOLEAN NOT NULL, is_inspector BOOLEAN NOT NULL, joined_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_C26F74BBA76ED395 ON user_club (user_id)');
        $this->addSql('CREATE INDEX IDX_C26F74BB61190A32 ON user_club (club_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_CLUB ON user_club (user_id, club_id)');
        $this->addSql('COMMENT ON COLUMN user_club.joined_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN user_club.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('ALTER TABLE user_club ADD CONSTRAINT FK_C26F74BBA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_club ADD CONSTRAINT FK_C26F74BB61190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE user_club DROP CONSTRAINT FK_C26F74BBA76ED395');
        $this->addSql('ALTER TABLE user_club DROP CONSTRAINT FK_C26F74BB61190A32');
        $this->addSql('DROP TABLE club');
        $this->addSql('DROP TABLE user_club');
    }
}
