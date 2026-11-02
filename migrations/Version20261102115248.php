<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102115248 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'POC Yack: PRO order access requests + company/SIRET on customers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE yack_order_access_request (id INT AUTO_INCREMENT NOT NULL, customer_id INT NOT NULL, status VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', decided_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', company VARCHAR(255) NOT NULL, siret VARCHAR(14) NOT NULL, full_name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, phone VARCHAR(32) NOT NULL, address LONGTEXT NOT NULL, message LONGTEXT DEFAULT NULL, INDEX IDX_BE5346C09395C3F3 (customer_id), INDEX yack_order_access_request_status_idx (status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE yack_order_access_request ADD CONSTRAINT FK_BE5346C09395C3F3 FOREIGN KEY (customer_id) REFERENCES sylius_customer (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sylius_customer ADD company VARCHAR(255) DEFAULT NULL, ADD siret VARCHAR(14) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yack_order_access_request DROP FOREIGN KEY FK_BE5346C09395C3F3');
        $this->addSql('DROP TABLE yack_order_access_request');
        $this->addSql('ALTER TABLE sylius_customer DROP company, DROP siret');
    }
}
