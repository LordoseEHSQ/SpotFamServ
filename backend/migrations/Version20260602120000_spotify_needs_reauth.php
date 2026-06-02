<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds needs_reauth flag to spotify_account_link. Drives the connection status by real re-auth
 * need instead of the short-lived access-token clock (#25, D-014). Additive, default false →
 * existing connected links stay connected.
 */
final class Version20260602120000_spotify_needs_reauth extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add needs_reauth boolean (default false) to spotify_account_link (additive, no data loss).';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('spotify_account_link');
        if (!$table->hasColumn('needs_reauth')) {
            $this->addSql('ALTER TABLE spotify_account_link ADD needs_reauth BOOLEAN NOT NULL DEFAULT false');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('spotify_account_link');
        if ($table->hasColumn('needs_reauth')) {
            $this->addSql('ALTER TABLE spotify_account_link DROP needs_reauth');
        }
    }
}
