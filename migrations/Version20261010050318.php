<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Written by hand: Doctrine does not generate CHECK constraints.
 */
final class Version20261010050318 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Require an email address or a phone number';
    }

    public function up(Schema $schema): void
    {
        // Same rule as in ContactInput. The form shows a message, this protects the data.
        $this->addSql('ALTER TABLE contacts ADD CONSTRAINT ck_contacts_email_or_phone CHECK (email IS NOT NULL OR phone IS NOT NULL)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contacts DROP CONSTRAINT ck_contacts_email_or_phone');
    }
}
