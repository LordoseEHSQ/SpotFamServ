<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Device Governance Migration:
 * - Neue Tabelle spotify_device (persistierte Geräte mit Governance-Feldern)
 * - Neue Tabelle device_discovery_run (Discovery-Protokoll)
 * - Neue Tabelle activity_log (Aktivitäts-/Verlaufsprotokoll)
 * - Erweiterung family_profile um status-Feld
 *
 * Indexstrategie:
 * - BTREE auf FKs und häufig gefilterte Felder
 * - Partial Index auf deleted_at IS NULL (vorbereitet)
 * - GIN auf JSONB-Felder für effiziente JSON-Suche
 */
final class Version20250318000000_device_governance extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Device Governance: spotify_device, device_discovery_run, activity_log; Profile status-Feld';
    }

    public function up(Schema $schema): void
    {
        // ─── family_profile: status-Feld ──────────────────────────────────────
        $this->addSql(<<<SQL
            ALTER TABLE family_profile
                ADD COLUMN IF NOT EXISTS status VARCHAR(32) NOT NULL DEFAULT 'active'
        SQL);

        // ─── spotify_device ───────────────────────────────────────────────────
        $this->addSql(<<<SQL
            CREATE TABLE IF NOT EXISTS spotify_device (
                id                          UUID          NOT NULL,
                spotify_device_id           VARCHAR(255)  NOT NULL,
                spotify_device_name         VARCHAR(255)  NOT NULL,
                device_type                 VARCHAR(64)   DEFAULT NULL,
                is_available                BOOLEAN       NOT NULL DEFAULT FALSE,
                last_seen_at                TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                assigned_family_profile_id  UUID          DEFAULT NULL,
                assignment_mode             VARCHAR(32)   NOT NULL DEFAULT 'unassigned',
                assignment_updated_at       TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                assignment_note             TEXT          DEFAULT NULL,
                discovery_status            VARCHAR(32)   NOT NULL DEFAULT 'unknown',
                last_discovery_run_id       UUID          DEFAULT NULL,
                created_at                  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at                  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_spotify_device_id ON spotify_device (spotify_device_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_spotify_device_profile ON spotify_device (assigned_family_profile_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_spotify_device_available ON spotify_device (is_available, assignment_mode)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_spotify_device_last_seen ON spotify_device (last_seen_at DESC)');

        $this->addSql(<<<SQL
            ALTER TABLE spotify_device
                ADD CONSTRAINT fk_spotify_device_profile
                FOREIGN KEY (assigned_family_profile_id)
                REFERENCES family_profile(id)
                ON DELETE SET NULL
                NOT VALID
        SQL);

        // ─── device_discovery_run ─────────────────────────────────────────────
        $this->addSql(<<<SQL
            CREATE TABLE IF NOT EXISTS device_discovery_run (
                id                       UUID          NOT NULL,
                started_at               TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                finished_at              TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                scope                    VARCHAR(64)   NOT NULL DEFAULT 'global',
                scope_profile_id         UUID          DEFAULT NULL,
                result_status            VARCHAR(32)   DEFAULT NULL,
                devices_found_count      INTEGER       NOT NULL DEFAULT 0,
                devices_available_count  INTEGER       NOT NULL DEFAULT 0,
                devices_new_count        INTEGER       NOT NULL DEFAULT 0,
                error_message            TEXT          DEFAULT NULL,
                raw_payload              JSONB         DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_discovery_run_started ON device_discovery_run (started_at DESC)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_discovery_run_scope_profile ON device_discovery_run (scope_profile_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_discovery_run_raw_payload ON device_discovery_run USING GIN (raw_payload) WHERE raw_payload IS NOT NULL');

        // ─── activity_log ─────────────────────────────────────────────────────
        $this->addSql(<<<SQL
            CREATE TABLE IF NOT EXISTS activity_log (
                id                   UUID          NOT NULL,
                family_profile_id    UUID          DEFAULT NULL,
                related_entity_type  VARCHAR(64)   DEFAULT NULL,
                related_entity_id    VARCHAR(255)  DEFAULT NULL,
                activity_type        VARCHAR(64)   NOT NULL,
                severity             VARCHAR(16)   NOT NULL DEFAULT 'info',
                message              VARCHAR(512)  NOT NULL,
                details              JSONB         DEFAULT NULL,
                occurred_at          TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_activity_log_profile ON activity_log (family_profile_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_activity_log_occurred ON activity_log (occurred_at DESC)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_activity_log_severity ON activity_log (severity, occurred_at DESC)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_activity_log_type ON activity_log (activity_type, occurred_at DESC)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_activity_log_entity ON activity_log (related_entity_type, related_entity_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_activity_log_details ON activity_log USING GIN (details) WHERE details IS NOT NULL');

        $this->addSql(<<<SQL
            ALTER TABLE activity_log
                ADD CONSTRAINT fk_activity_log_profile
                FOREIGN KEY (family_profile_id)
                REFERENCES family_profile(id)
                ON DELETE SET NULL
                NOT VALID
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_log DROP CONSTRAINT IF EXISTS fk_activity_log_profile');
        $this->addSql('ALTER TABLE spotify_device DROP CONSTRAINT IF EXISTS fk_spotify_device_profile');
        $this->addSql('DROP TABLE IF EXISTS activity_log');
        $this->addSql('DROP TABLE IF EXISTS device_discovery_run');
        $this->addSql('DROP TABLE IF EXISTS spotify_device');
        $this->addSql('ALTER TABLE family_profile DROP COLUMN IF EXISTS status');
    }
}
