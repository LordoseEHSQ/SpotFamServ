<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds diagnostic columns to reader_device for admin UI status view.
 * All new columns are nullable so existing readers are not broken.
 */
final class Version20260608180000_reader_device_diagnostics extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add last_seen_at, firmware_version, board, fw_channel, last_ip to reader_device.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('reader_device');

        if (!$table->hasColumn('last_seen_at')) {
            $this->addSql('ALTER TABLE reader_device ADD COLUMN last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        }
        if (!$table->hasColumn('firmware_version')) {
            $this->addSql('ALTER TABLE reader_device ADD COLUMN firmware_version VARCHAR(20) DEFAULT NULL');
        }
        if (!$table->hasColumn('board')) {
            $this->addSql('ALTER TABLE reader_device ADD COLUMN board VARCHAR(64) DEFAULT NULL');
        }
        if (!$table->hasColumn('fw_channel')) {
            $this->addSql('ALTER TABLE reader_device ADD COLUMN fw_channel VARCHAR(32) DEFAULT NULL');
        }
        if (!$table->hasColumn('last_ip')) {
            $this->addSql('ALTER TABLE reader_device ADD COLUMN last_ip VARCHAR(45) DEFAULT NULL');
        }
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_reader_device_last_seen ON reader_device (last_seen_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_reader_device_last_seen');
        $this->addSql('ALTER TABLE reader_device DROP COLUMN IF EXISTS last_seen_at');
        $this->addSql('ALTER TABLE reader_device DROP COLUMN IF EXISTS firmware_version');
        $this->addSql('ALTER TABLE reader_device DROP COLUMN IF EXISTS board');
        $this->addSql('ALTER TABLE reader_device DROP COLUMN IF EXISTS fw_channel');
        $this->addSql('ALTER TABLE reader_device DROP COLUMN IF EXISTS last_ip');
    }
}
