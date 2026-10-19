<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261010042801 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add contact detail columns and the QR code image';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contacts ADD email VARCHAR(254) DEFAULT NULL, ADD phone VARCHAR(32) DEFAULT NULL, ADD birthday DATE DEFAULT NULL, ADD street VARCHAR(200) DEFAULT NULL, ADD postal_code VARCHAR(20) DEFAULT NULL, ADD city VARCHAR(100) DEFAULT NULL, ADD country VARCHAR(100) DEFAULT NULL, ADD organization VARCHAR(150) DEFAULT NULL, ADD job_title VARCHAR(100) DEFAULT NULL, ADD qr_png MEDIUMBLOB NOT NULL');
        $this->addSql('ALTER TABLE contacts ADD CONSTRAINT ck_contacts_qr_png_not_empty CHECK (OCTET_LENGTH(qr_png) > 0)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contacts DROP CONSTRAINT ck_contacts_qr_png_not_empty');
        $this->addSql('ALTER TABLE contacts DROP email, DROP phone, DROP birthday, DROP street, DROP postal_code, DROP city, DROP country, DROP organization, DROP job_title, DROP qr_png');
    }
}
