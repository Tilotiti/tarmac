<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013163536 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adresses e-mail des utilisateurs et des invitations en minuscules';
    }

    public function up(Schema $schema): void
    {
        // Deux comptes qui ne diffèrent que par la casse violeraient l'index unique : à fusionner à la main avant
        $duplicates = $this->connection->fetchFirstColumn('SELECT LOWER(email) FROM "user" GROUP BY LOWER(email) HAVING COUNT(*) > 1');
        $this->abortIf($duplicates !== [], 'Comptes en double à fusionner avant la migration : ' . implode(', ', $duplicates));

        $this->addSql('UPDATE "user" SET email = LOWER(email) WHERE email <> LOWER(email)');
        $this->addSql('UPDATE invitation SET email = LOWER(email) WHERE email <> LOWER(email)');
    }

    public function down(Schema $schema): void
    {
        // La casse d'origine est perdue, rien à restaurer
    }
}
