<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261108231500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'POC Yack: edit locks of the builder (yack_cms_edit_lock)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE yack_cms_edit_lock (id INT AUTO_INCREMENT NOT NULL, page_id INT DEFAULT NULL, template_id INT DEFAULT NULL, admin_id INT NOT NULL, token VARCHAR(32) NOT NULL, acquired_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', heartbeat_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', version INT NOT NULL, INDEX IDX_A5B478A4642B8210 (admin_id), UNIQUE INDEX uniq_yack_cms_edit_lock_page (page_id), UNIQUE INDEX uniq_yack_cms_edit_lock_template (template_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE yack_cms_edit_lock ADD CONSTRAINT FK_A5B478A4C4663E4 FOREIGN KEY (page_id) REFERENCES yack_cms_page (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE yack_cms_edit_lock ADD CONSTRAINT FK_A5B478A45DA0FB8 FOREIGN KEY (template_id) REFERENCES yack_cms_template (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE yack_cms_edit_lock ADD CONSTRAINT FK_A5B478A4642B8210 FOREIGN KEY (admin_id) REFERENCES sylius_admin_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE yack_cms_edit_lock');
    }
}
