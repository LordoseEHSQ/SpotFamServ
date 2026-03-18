<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250315200000_rfid_scan extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reader_device, spotify_playlist_reference, card_playlist_binding; extend scan_event with optional reader_device_id, rfid_card_id, family_profile_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE reader_device (
            id UUID NOT NULL,
            reader_id VARCHAR(64) NOT NULL,
            name VARCHAR(255) DEFAULT NULL,
            api_key_hash VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_reader_device_reader_id ON reader_device (reader_id)');

        $this->addSql('CREATE TABLE spotify_playlist_reference (
            id UUID NOT NULL,
            family_profile_id UUID NOT NULL,
            spotify_playlist_id VARCHAR(255) NOT NULL,
            name VARCHAR(255) NOT NULL,
            owner_id VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_spotify_playlist_ref_profile_playlist ON spotify_playlist_reference (family_profile_id, spotify_playlist_id)');

        $this->addSql('CREATE TABLE card_playlist_binding (
            id UUID NOT NULL,
            rfid_card_id UUID NOT NULL,
            spotify_playlist_reference_id UUID NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_card_playlist_binding_rfid_card ON card_playlist_binding (rfid_card_id)');

        $this->addSql('ALTER TABLE scan_event ADD reader_device_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE scan_event ADD rfid_card_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE scan_event ADD family_profile_id UUID DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE scan_event DROP reader_device_id');
        $this->addSql('ALTER TABLE scan_event DROP rfid_card_id');
        $this->addSql('ALTER TABLE scan_event DROP family_profile_id');
        $this->addSql('DROP TABLE card_playlist_binding');
        $this->addSql('DROP TABLE spotify_playlist_reference');
        $this->addSql('DROP TABLE reader_device');
    }
}
