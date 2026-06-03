<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds short-lived reader claims for ESP consumer provisioning.
 * Claims are one-time exchange tokens; the long-lived secret remains the
 * per-reader API key hash on reader_device.
 */
final class Version20260603110000_reader_claims extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reader_claim table for short-lived ESP reader provisioning claims.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('reader_claim')) {
            return;
        }

        $this->addSql('CREATE TABLE reader_claim (
            id UUID NOT NULL,
            claim_code_hash VARCHAR(64) NOT NULL,
            reader_id VARCHAR(64) DEFAULT NULL,
            reader_name VARCHAR(255) DEFAULT NULL,
            fw_channel VARCHAR(32) NOT NULL,
            activation_attempts INT NOT NULL,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_reader_claim_code_hash ON reader_claim (claim_code_hash)');
        $this->addSql('CREATE INDEX idx_reader_claim_expires ON reader_claim (expires_at)');
        $this->addSql('CREATE INDEX idx_reader_claim_reader_id ON reader_claim (reader_id)');
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('reader_claim')) {
            $this->addSql('DROP TABLE reader_claim');
        }
    }
}
