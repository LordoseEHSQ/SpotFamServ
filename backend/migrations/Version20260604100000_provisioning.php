<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Legt die drei Provisioning-Tabellen an (Flash-Station Phase 2).
 * Idempotent: Existenz jeder Tabelle wird vor der Anlage geprüft.
 */
final class Version20260604100000_provisioning extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Provisioning-Modul: provisioning_detected_device, provisioning_flash_artifact, provisioning_flash_job';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('provisioning_detected_device')) {
            $this->addSql('CREATE TABLE provisioning_detected_device (
                id UUID NOT NULL,
                port VARCHAR(64) NOT NULL,
                chip VARCHAR(64) NOT NULL,
                chip_description VARCHAR(255) NOT NULL,
                mac VARCHAR(32) NOT NULL,
                flash_size VARCHAR(32) NOT NULL,
                first_seen_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT \'idle\',
                PRIMARY KEY(id)
            )');
            $this->addSql('CREATE UNIQUE INDEX uniq_provisioning_device_mac ON provisioning_detected_device (mac)');
            $this->addSql('CREATE INDEX idx_provisioning_device_status ON provisioning_detected_device (status)');
        }

        if (!$schema->hasTable('provisioning_flash_artifact')) {
            $this->addSql('CREATE TABLE provisioning_flash_artifact (
                id UUID NOT NULL,
                board VARCHAR(64) NOT NULL,
                channel VARCHAR(32) NOT NULL,
                version VARCHAR(64) NOT NULL,
                filename VARCHAR(255) NOT NULL,
                sha256 VARCHAR(64) NOT NULL,
                expected_chip VARCHAR(64) NOT NULL,
                size_bytes INT NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )');
            $this->addSql('CREATE INDEX idx_provisioning_artifact_board_channel ON provisioning_flash_artifact (board, channel)');
        }

        if (!$schema->hasTable('provisioning_flash_job')) {
            $this->addSql('CREATE TABLE provisioning_flash_job (
                id UUID NOT NULL,
                device_id UUID NOT NULL,
                artifact_id UUID NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT \'pending\',
                progress INT NOT NULL DEFAULT 0,
                message TEXT DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id),
                CONSTRAINT fk_flash_job_device FOREIGN KEY (device_id)
                    REFERENCES provisioning_detected_device (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT fk_flash_job_artifact FOREIGN KEY (artifact_id)
                    REFERENCES provisioning_flash_artifact (id) NOT DEFERRABLE INITIALLY IMMEDIATE
            )');
            $this->addSql('CREATE INDEX idx_flash_job_device_status ON provisioning_flash_job (device_id, status)');
            $this->addSql('CREATE INDEX idx_flash_job_created ON provisioning_flash_job (created_at)');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('provisioning_flash_job')) {
            $this->addSql('DROP TABLE provisioning_flash_job');
        }

        if ($schema->hasTable('provisioning_flash_artifact')) {
            $this->addSql('DROP TABLE provisioning_flash_artifact');
        }

        if ($schema->hasTable('provisioning_detected_device')) {
            $this->addSql('DROP TABLE provisioning_detected_device');
        }
    }
}
