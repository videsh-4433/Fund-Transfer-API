<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260427121026 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE account (id INT AUTO_INCREMENT NOT NULL, balance NUMERIC(15, 2) NOT NULL, currency VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE transfer (id INT AUTO_INCREMENT NOT NULL, amount NUMERIC(15, 2) NOT NULL, status VARCHAR(255) NOT NULL, reference_id VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, source_account_id INT NOT NULL, destination_account_id INT NOT NULL, UNIQUE INDEX UNIQ_4034A3C01645DEA9 (reference_id), INDEX IDX_4034A3C0E7DF2E9E (source_account_id), INDEX IDX_4034A3C0C652C408 (destination_account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE transfer ADD CONSTRAINT FK_4034A3C0E7DF2E9E FOREIGN KEY (source_account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE transfer ADD CONSTRAINT FK_4034A3C0C652C408 FOREIGN KEY (destination_account_id) REFERENCES account (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE transfer DROP FOREIGN KEY FK_4034A3C0E7DF2E9E');
        $this->addSql('ALTER TABLE transfer DROP FOREIGN KEY FK_4034A3C0C652C408');
        $this->addSql('DROP TABLE account');
        $this->addSql('DROP TABLE transfer');
    }
}
