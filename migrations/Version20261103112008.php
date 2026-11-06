<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261103112008 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'POC Yack: intra-community VAT number on customers (registration)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_customer ADD vat_number VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_customer DROP vat_number');
    }
}
