<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sprint 07 / D-032: AudioJob – lifecycle of an asynchronous extraction (Messenger queue).
 * The messenger_messages transport table is created separately via
 * `messenger:setup-transports` (auto_setup), not here.
 */
final class Version20260605140000_audio_job extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'AudioExtractor: audio_job (async extraction lifecycle, D-032)';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('audio_job')) {
            $this->addSql('CREATE TABLE audio_job (
                id UUID NOT NULL,
                url TEXT NOT NULL,
                format VARCHAR(8) NOT NULL,
                bitrate_kbps INT DEFAULT NULL,
                status VARCHAR(16) NOT NULL DEFAULT \'pending\',
                progress INT NOT NULL DEFAULT 0,
                error TEXT DEFAULT NULL,
                result_file VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )');
            $this->addSql('CREATE INDEX idx_audio_job_status ON audio_job (status)');
            $this->addSql('CREATE INDEX idx_audio_job_created ON audio_job (created_at)');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('audio_job')) {
            $this->addSql('DROP TABLE audio_job');
        }
    }
}
