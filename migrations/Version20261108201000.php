<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261108201000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'POC Yack: history of the CMS pages and templates (yack_cms_revision)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE yack_cms_revision (id INT AUTO_INCREMENT NOT NULL, page_id INT DEFAULT NULL, template_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, status VARCHAR(16) NOT NULL, block_count INT NOT NULL, content JSON NOT NULL, author_first_name VARCHAR(255) DEFAULT NULL, author_last_name VARCHAR(255) DEFAULT NULL, author_name VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_99C0C141C4663E4 (page_id), INDEX IDX_99C0C1415DA0FB8 (template_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE yack_cms_revision ADD CONSTRAINT FK_99C0C141C4663E4 FOREIGN KEY (page_id) REFERENCES yack_cms_page (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE yack_cms_revision ADD CONSTRAINT FK_99C0C1415DA0FB8 FOREIGN KEY (template_id) REFERENCES yack_cms_template (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE yack_cms_revision');
    }
}
