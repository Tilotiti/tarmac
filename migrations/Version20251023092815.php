<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251023092815 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE user_club_id_seq CASCADE');
        $this->addSql('CREATE TABLE membership (id SERIAL NOT NULL, user_id INT NOT NULL, club_id INT NOT NULL, is_manager BOOLEAN NOT NULL, is_inspector BOOLEAN NOT NULL, joined_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_86FFD285A76ED395 ON membership (user_id)');
        $this->addSql('CREATE INDEX IDX_86FFD28561190A32 ON membership (club_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_MEMBERSHIP ON membership (user_id, club_id)');
        $this->addSql('COMMENT ON COLUMN membership.joined_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN membership.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('ALTER TABLE membership ADD CONSTRAINT FK_86FFD285A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE membership ADD CONSTRAINT FK_86FFD28561190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_club DROP CONSTRAINT fk_c26f74bb61190a32');
        $this->addSql('ALTER TABLE user_club DROP CONSTRAINT fk_c26f74bba76ed395');
        $this->addSql('DROP TABLE user_club');
        $this->addSql('ALTER TABLE "user" DROP invitation_token');
        $this->addSql('CREATE INDEX sess_lifetime_idx ON sessions (sess_lifetime)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE SEQUENCE user_club_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE user_club (id SERIAL NOT NULL, user_id INT NOT NULL, club_id INT NOT NULL, is_manager BOOLEAN NOT NULL, is_inspector BOOLEAN NOT NULL, joined_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_c26f74bb61190a32 ON user_club (club_id)');
        $this->addSql('CREATE INDEX idx_c26f74bba76ed395 ON user_club (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_club ON user_club (user_id, club_id)');
        $this->addSql('COMMENT ON COLUMN user_club.joined_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN user_club.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('ALTER TABLE user_club ADD CONSTRAINT fk_c26f74bb61190a32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_club ADD CONSTRAINT fk_c26f74bba76ed395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE membership DROP CONSTRAINT FK_86FFD285A76ED395');
        $this->addSql('ALTER TABLE membership DROP CONSTRAINT FK_86FFD28561190A32');
        $this->addSql('DROP TABLE membership');
        $this->addSql('DROP INDEX sess_lifetime_idx');
        $this->addSql('ALTER TABLE "user" ADD invitation_token VARCHAR(100) DEFAULT NULL');
    }
}
