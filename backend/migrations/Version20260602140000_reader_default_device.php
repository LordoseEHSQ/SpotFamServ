<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds an optional default Spotify playback device (room box) to reader_device.
 * Enables Reader→Box mapping for multi-room (D-015): a scan plays on the reader's
 * box instead of the card profile's default. Additive + nullable → existing readers
 * keep falling back to the profile default.
 */
final class Version20260602140000_reader_default_device extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add default_spotify_device_id + default_device_name to reader_device (additive, nullable, no data loss).';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('reader_device');
        if (!$table->hasColumn('default_spotify_device_id')) {
            $this->addSql('ALTER TABLE reader_device ADD default_spotify_device_id VARCHAR(255) DEFAULT NULL');
        }
        if (!$table->hasColumn('default_device_name')) {
            $this->addSql('ALTER TABLE reader_device ADD default_device_name VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('reader_device');
        if ($table->hasColumn('default_device_name')) {
            $this->addSql('ALTER TABLE reader_device DROP default_device_name');
        }
        if ($table->hasColumn('default_spotify_device_id')) {
            $this->addSql('ALTER TABLE reader_device DROP default_spotify_device_id');
        }
    }
}
