<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260315102548 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE plan_sub_task_specialisation (plan_sub_task_id INT NOT NULL, specialisation_id INT NOT NULL, PRIMARY KEY(plan_sub_task_id, specialisation_id))');
        $this->addSql('CREATE INDEX IDX_A6A97FA16D78519 ON plan_sub_task_specialisation (plan_sub_task_id)');
        $this->addSql('CREATE INDEX IDX_A6A97FA15627D44C ON plan_sub_task_specialisation (specialisation_id)');
        $this->addSql('CREATE TABLE specialisation (id SERIAL NOT NULL, club_id INT NOT NULL, name VARCHAR(80) NOT NULL, description TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_B9D6A3A261190A32 ON specialisation (club_id)');
        $this->addSql('CREATE TABLE sub_task_specialisation (sub_task_id INT NOT NULL, specialisation_id INT NOT NULL, PRIMARY KEY(sub_task_id, specialisation_id))');
        $this->addSql('CREATE INDEX IDX_F30C8C3CF26E5D72 ON sub_task_specialisation (sub_task_id)');
        $this->addSql('CREATE INDEX IDX_F30C8C3C5627D44C ON sub_task_specialisation (specialisation_id)');
        $this->addSql('ALTER TABLE plan_sub_task_specialisation ADD CONSTRAINT FK_A6A97FA16D78519 FOREIGN KEY (plan_sub_task_id) REFERENCES plan_sub_task (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE plan_sub_task_specialisation ADD CONSTRAINT FK_A6A97FA15627D44C FOREIGN KEY (specialisation_id) REFERENCES specialisation (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE specialisation ADD CONSTRAINT FK_B9D6A3A261190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sub_task_specialisation ADD CONSTRAINT FK_F30C8C3CF26E5D72 FOREIGN KEY (sub_task_id) REFERENCES sub_task (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sub_task_specialisation ADD CONSTRAINT FK_F30C8C3C5627D44C FOREIGN KEY (specialisation_id) REFERENCES specialisation (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP INDEX idx_75ea56e016ba31db');
        $this->addSql('DROP INDEX idx_75ea56e0e3bd61ce');
        $this->addSql('DROP INDEX idx_75ea56e0fb7336f0');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE plan_sub_task_specialisation DROP CONSTRAINT FK_A6A97FA16D78519');
        $this->addSql('ALTER TABLE plan_sub_task_specialisation DROP CONSTRAINT FK_A6A97FA15627D44C');
        $this->addSql('ALTER TABLE specialisation DROP CONSTRAINT FK_B9D6A3A261190A32');
        $this->addSql('ALTER TABLE sub_task_specialisation DROP CONSTRAINT FK_F30C8C3CF26E5D72');
        $this->addSql('ALTER TABLE sub_task_specialisation DROP CONSTRAINT FK_F30C8C3C5627D44C');
        $this->addSql('DROP TABLE plan_sub_task_specialisation');
        $this->addSql('DROP TABLE specialisation');
        $this->addSql('DROP TABLE sub_task_specialisation');
        $this->addSql('DROP INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750');
        $this->addSql('CREATE INDEX idx_75ea56e016ba31db ON messenger_messages (delivered_at)');
        $this->addSql('CREATE INDEX idx_75ea56e0e3bd61ce ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX idx_75ea56e0fb7336f0 ON messenger_messages (queue_name)');
    }
}
