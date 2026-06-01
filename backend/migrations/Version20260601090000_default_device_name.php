<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds default_device_name to family_profile so the chosen default Spotify device name
 * is persisted alongside its (ephemeral) Spotify device id. Enables name display without
 * a cross-module lookup and re-resolution of a stale device id by name (Sprint 2, #9 / D-009).
 */
final class Version20260601090000_default_device_name extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable default_device_name column to family_profile (additive, no data loss).';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('family_profile');
        if (!$table->hasColumn('default_device_name')) {
            $this->addSql('ALTER TABLE family_profile ADD default_device_name VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('family_profile');
        if ($table->hasColumn('default_device_name')) {
            $this->addSql('ALTER TABLE family_profile DROP default_device_name');
        }
    }
}
