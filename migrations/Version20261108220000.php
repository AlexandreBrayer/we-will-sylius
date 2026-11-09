<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261108220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'POC Yack: reusable sections of the builder (yack_cms_section)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE yack_cms_section (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, blocks JSON NOT NULL, author_name VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE yack_cms_section');
    }
}
