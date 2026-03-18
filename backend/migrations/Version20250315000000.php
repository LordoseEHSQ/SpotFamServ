<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250315000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: admin_user, family_profile, spotify_account_link, rfid_card, scan_event, profile_setup_session, profile_setup_step_status';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE admin_user (
            id UUID NOT NULL,
            username VARCHAR(180) NOT NULL,
            password VARCHAR(255) NOT NULL,
            roles JSON NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_admin_user_username ON admin_user (username)');

        $this->addSql('CREATE TABLE family_profile (
            id UUID NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            default_spotify_device_id VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE TABLE spotify_account_link (
            id UUID NOT NULL,
            family_profile_id UUID NOT NULL,
            spotify_user_id VARCHAR(255) NOT NULL,
            access_token TEXT NOT NULL,
            refresh_token TEXT NOT NULL,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_spotify_account_link_family_profile ON spotify_account_link (family_profile_id)');

        $this->addSql('CREATE TABLE rfid_card (
            id UUID NOT NULL,
            family_profile_id UUID NOT NULL,
            card_uid VARCHAR(64) NOT NULL,
            label VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_rfid_card_card_uid ON rfid_card (card_uid)');

        $this->addSql('CREATE TABLE scan_event (
            id UUID NOT NULL,
            card_uid_raw VARCHAR(64) NOT NULL,
            outcome VARCHAR(64) NOT NULL,
            details JSON DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE TABLE profile_setup_session (
            id UUID NOT NULL,
            family_profile_id UUID NOT NULL,
            current_step VARCHAR(64) NOT NULL,
            status VARCHAR(32) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_profile_setup_session_family_profile ON profile_setup_session (family_profile_id)');

        $this->addSql('CREATE TABLE profile_setup_step_status (
            id UUID NOT NULL,
            profile_setup_session_id UUID NOT NULL,
            step_key VARCHAR(64) NOT NULL,
            status VARCHAR(32) NOT NULL,
            payload JSON DEFAULT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_profile_setup_step_session_key ON profile_setup_step_status (profile_setup_session_id, step_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE profile_setup_step_status');
        $this->addSql('DROP TABLE profile_setup_session');
        $this->addSql('DROP TABLE scan_event');
        $this->addSql('DROP TABLE rfid_card');
        $this->addSql('DROP TABLE spotify_account_link');
        $this->addSql('DROP TABLE family_profile');
        $this->addSql('DROP TABLE admin_user');
    }
}
