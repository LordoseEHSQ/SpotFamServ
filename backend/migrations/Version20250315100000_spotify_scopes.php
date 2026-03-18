<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250315100000_spotify_scopes extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add scopes column to spotify_account_link; backfill existing rows for encrypted type (no-op if already text).';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('spotify_account_link');
        if (!$table->hasColumn('scopes')) {
            $this->addSql('ALTER TABLE spotify_account_link ADD scopes VARCHAR(512) DEFAULT NULL');
        }
        // Note: Changing access_token/refresh_token from text to encrypted type is done at application level
        // (EncryptedStringType still stores as CLOB/text). Existing plaintext tokens will be read as "plain"
        // and re-stored encrypted on next update. For fresh installs, type is spotify_encrypted_string from start.
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('spotify_account_link');
        if ($table->hasColumn('scopes')) {
            $this->addSql('ALTER TABLE spotify_account_link DROP scopes');
        }
    }
}
