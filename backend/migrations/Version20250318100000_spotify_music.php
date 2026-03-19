<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Spotify Music & System-Config Migration:
 * - Neue Tabelle spotify_app_configuration (systemweite Client Credentials)
 * - Erweiterung spotify_account_link um spotify_display_name, last_validated_at
 *
 * Indexstrategie:
 * - BTREE auf is_active (Singleton-Lookup)
 * - BTREE auf family_profile_id (bereits vorhanden, unique constraint)
 */
final class Version20250318100000_spotify_music extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Spotify Music: spotify_app_configuration Tabelle; spotify_account_link um display_name, last_validated_at erweitert';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE spotify_app_configuration (
                id UUID NOT NULL DEFAULT gen_random_uuid(),
                spotify_client_id VARCHAR(255) DEFAULT NULL,
                spotify_client_secret TEXT DEFAULT NULL,
                redirect_uri VARCHAR(512) DEFAULT NULL,
                scope_defaults VARCHAR(1024) DEFAULT NULL,
                config_status VARCHAR(32) NOT NULL DEFAULT 'unconfigured',
                last_check_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                last_check_note VARCHAR(512) DEFAULT NULL,
                is_active BOOLEAN NOT NULL DEFAULT TRUE,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_spotify_app_config_active ON spotify_app_configuration (is_active)');
        $this->addSql("COMMENT ON COLUMN spotify_app_configuration.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN spotify_app_configuration.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN spotify_app_configuration.last_check_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql('ALTER TABLE spotify_account_link ADD COLUMN spotify_display_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE spotify_account_link ADD COLUMN last_validated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql("COMMENT ON COLUMN spotify_account_link.last_validated_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS spotify_app_configuration');
        $this->addSql('ALTER TABLE spotify_account_link DROP COLUMN IF EXISTS spotify_display_name');
        $this->addSql('ALTER TABLE spotify_account_link DROP COLUMN IF EXISTS last_validated_at');
    }
}
