<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251027085220 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix missing activity_id_seq sequence that was dropped during table rename';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE activity_id_seq');
        $this->addSql('SELECT setval(\'activity_id_seq\', GREATEST(COALESCE((SELECT MAX(id) FROM activity), 0), 1))');
        $this->addSql('ALTER TABLE activity ALTER id SET DEFAULT nextval(\'activity_id_seq\')');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE activity ALTER id DROP DEFAULT');
    }
}
