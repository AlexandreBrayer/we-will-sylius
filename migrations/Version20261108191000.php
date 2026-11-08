<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261108191000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'POC Yack: media library of the CMS builder (yack_cms_media)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE yack_cms_media (id INT AUTO_INCREMENT NOT NULL, path VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, media_type VARCHAR(32) NOT NULL, width INT NOT NULL, height INT NOT NULL, size INT NOT NULL, alt VARCHAR(255) NOT NULL, author_name VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_80976AD6B548B0F (path), INDEX idx_yack_cms_media_created_at (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE yack_cms_media');
    }
}
