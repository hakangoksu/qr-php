<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261010062348 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the request log table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE request_logs (id BIGINT AUTO_INCREMENT NOT NULL, timestamp DATETIME(6) NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, method VARCHAR(10) NOT NULL, path VARCHAR(2048) NOT NULL, status_code SMALLINT NOT NULL, user_agent VARCHAR(512) DEFAULT NULL, source VARCHAR(3) NOT NULL, duration_ms DOUBLE PRECISION NOT NULL, INDEX ix_request_logs_timestamp (timestamp), INDEX ix_request_logs_source_timestamp (source, timestamp), INDEX ix_request_logs_status_code_timestamp (status_code, timestamp), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        // Written by hand: Doctrine does not model CHECK constraints.
        $this->addSql("ALTER TABLE request_logs ADD CONSTRAINT ck_request_logs_source CHECK (source IN ('UI', 'API'))");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE request_logs');
    }
}
