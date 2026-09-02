<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902111406 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Club : aérodrome, coordonnées, commune libre et créateur';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE club ADD location_label VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE club ADD latitude DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE club ADD longitude DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE club ADD aerodrome_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE club ADD created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE club ADD CONSTRAINT FK_B8EE3872BA69BD81 FOREIGN KEY (aerodrome_id) REFERENCES aerodrome (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE club ADD CONSTRAINT FK_B8EE3872B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_B8EE3872BA69BD81 ON club (aerodrome_id)');
        $this->addSql('CREATE INDEX IDX_B8EE3872B03A8386 ON club (created_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE club DROP CONSTRAINT FK_B8EE3872BA69BD81');
        $this->addSql('ALTER TABLE club DROP CONSTRAINT FK_B8EE3872B03A8386');
        $this->addSql('DROP INDEX IDX_B8EE3872BA69BD81');
        $this->addSql('DROP INDEX IDX_B8EE3872B03A8386');
        $this->addSql('ALTER TABLE club DROP location_label');
        $this->addSql('ALTER TABLE club DROP latitude');
        $this->addSql('ALTER TABLE club DROP longitude');
        $this->addSql('ALTER TABLE club DROP aerodrome_id');
        $this->addSql('ALTER TABLE club DROP created_by_id');
    }
}
