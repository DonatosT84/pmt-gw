<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial schema: payments aggregate + one row per available payment provider.
 */
final class Version20260909185612 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create payments and payment_providers tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE payments (
                id CHAR(36) NOT NULL,
                amount_minor_units INTEGER NOT NULL,
                amount_currency VARCHAR(3) NOT NULL,
                payer_email VARCHAR(255) NOT NULL,
                description VARCHAR(500) DEFAULT NULL,
                provider_id INTEGER NOT NULL,
                provider_name VARCHAR(32) NOT NULL,
                provider_reference VARCHAR(255) DEFAULT NULL,
                status VARCHAR(20) NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE payment_providers (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                name VARCHAR(32) NOT NULL,
                is_default BOOLEAN NOT NULL
            )
            SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO payment_providers (id, name, is_default) VALUES (1, 'stripe', 1), (2, 'paypal', 0)
            SQL);

        $this->addSql('CREATE UNIQUE INDEX UNIQ_A10CFB3F5E237E06 ON payment_providers (name)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE payments');
        $this->addSql('DROP TABLE payment_providers');
    }
}
