<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'POC Yack: cards saved by the customers for one-click payments (yack_saved_card: provider token + masked card)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE yack_saved_card (id INT AUTO_INCREMENT NOT NULL, customer_id INT NOT NULL, brand VARCHAR(16) NOT NULL, last4 VARCHAR(4) NOT NULL, expiry_month SMALLINT NOT NULL, expiry_year SMALLINT NOT NULL, token VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_4C31F5BF9395C3F3 (customer_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE yack_saved_card ADD CONSTRAINT FK_4C31F5BF9395C3F3 FOREIGN KEY (customer_id) REFERENCES sylius_customer (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE yack_saved_card');
    }
}
