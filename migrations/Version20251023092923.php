<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251023092923 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE invitation (id SERIAL NOT NULL, club_id INT NOT NULL, accepted_by_id INT DEFAULT NULL, email VARCHAR(180) NOT NULL, firstname VARCHAR(255) DEFAULT NULL, lastname VARCHAR(255) DEFAULT NULL, token VARCHAR(100) NOT NULL, is_manager BOOLEAN NOT NULL, is_inspector BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F11D61A25F37A13B ON invitation (token)');
        $this->addSql('CREATE INDEX IDX_F11D61A261190A32 ON invitation (club_id)');
        $this->addSql('CREATE INDEX IDX_F11D61A220F699D9 ON invitation (accepted_by_id)');
        $this->addSql('CREATE INDEX IDX_INVITATION_TOKEN ON invitation (token)');
        $this->addSql('COMMENT ON COLUMN invitation.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN invitation.expires_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('ALTER TABLE invitation ADD CONSTRAINT FK_F11D61A261190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE invitation ADD CONSTRAINT FK_F11D61A220F699D9 FOREIGN KEY (accepted_by_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE invitation DROP CONSTRAINT FK_F11D61A261190A32');
        $this->addSql('ALTER TABLE invitation DROP CONSTRAINT FK_F11D61A220F699D9');
        $this->addSql('DROP TABLE invitation');
    }
}
