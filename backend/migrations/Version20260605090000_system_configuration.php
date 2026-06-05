<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Legt die Singleton-Tabelle system_configuration an (Sprint 06 / D-029):
 * Reader-Netzwerk (WLAN/Backend/OTA) + Frontend-URL. wifi_password ist verschlüsselt
 * at rest (Doctrine-Type spotify_encrypted_string → TEXT/CLOB).
 * Idempotent: Existenz wird vor der Anlage geprüft.
 */
final class Version20260605090000_system_configuration extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'System-Modul: system_configuration (Reader-Netzwerk + Frontend-URL, D-029)';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('system_configuration')) {
            $this->addSql('CREATE TABLE system_configuration (
                id UUID NOT NULL,
                wifi_ssid VARCHAR(64) DEFAULT NULL,
                wifi_password TEXT DEFAULT NULL,
                backend_base_url VARCHAR(255) DEFAULT NULL,
                ota_channel VARCHAR(32) NOT NULL DEFAULT \'stable\',
                frontend_url VARCHAR(512) DEFAULT NULL,
                is_active BOOLEAN NOT NULL DEFAULT true,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )');
            $this->addSql('CREATE UNIQUE INDEX uniq_system_configuration_active ON system_configuration (is_active) WHERE is_active = true');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('system_configuration')) {
            $this->addSql('DROP TABLE system_configuration');
        }
    }
}
