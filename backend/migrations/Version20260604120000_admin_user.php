<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Legt die Tabelle admin_user an (Session-Auth, D-026).
 * Idempotent: hasTable-Check verhindert Fehler bei Mehrfach-Ausführung.
 */
final class Version20260604120000_admin_user extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Admin-Auth: Tabelle admin_user anlegen (D-026)';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('admin_user')) {
            return;
        }

        $this->addSql(<<<'SQL'
            CREATE TABLE admin_user (
                id         UUID         NOT NULL,
                username   VARCHAR(180) NOT NULL,
                password   VARCHAR(255) NOT NULL,
                roles      JSON         NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql("COMMENT ON COLUMN admin_user.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN admin_user.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN admin_user.updated_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql('CREATE UNIQUE INDEX uniq_admin_user_username ON admin_user (username)');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('admin_user')) {
            return;
        }

        $this->addSql('DROP TABLE admin_user');
    }
}
