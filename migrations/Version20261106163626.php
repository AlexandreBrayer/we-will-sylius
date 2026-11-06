<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106163626 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'POC Yack: CMS templates (product / category pages) and their assignment to products and taxons';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE yack_cms_template (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(16) NOT NULL, name VARCHAR(255) NOT NULL, is_default TINYINT(1) NOT NULL, status VARCHAR(16) NOT NULL, blocks JSON NOT NULL, grapes_data JSON DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_yack_cms_template_type_status (type, status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE sylius_product ADD cms_template_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_product ADD CONSTRAINT FK_677B9B74983BB86C FOREIGN KEY (cms_template_id) REFERENCES yack_cms_template (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_677B9B74983BB86C ON sylius_product (cms_template_id)');
        $this->addSql('ALTER TABLE sylius_taxon ADD cms_template_id INT DEFAULT NULL, ADD product_cms_template_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_taxon ADD CONSTRAINT FK_CFD811CA983BB86C FOREIGN KEY (cms_template_id) REFERENCES yack_cms_template (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE sylius_taxon ADD CONSTRAINT FK_CFD811CAAAB0BDF1 FOREIGN KEY (product_cms_template_id) REFERENCES yack_cms_template (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_CFD811CA983BB86C ON sylius_taxon (cms_template_id)');
        $this->addSql('CREATE INDEX IDX_CFD811CAAAB0BDF1 ON sylius_taxon (product_cms_template_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_product DROP FOREIGN KEY FK_677B9B74983BB86C');
        $this->addSql('ALTER TABLE sylius_taxon DROP FOREIGN KEY FK_CFD811CA983BB86C');
        $this->addSql('ALTER TABLE sylius_taxon DROP FOREIGN KEY FK_CFD811CAAAB0BDF1');
        $this->addSql('DROP INDEX IDX_677B9B74983BB86C ON sylius_product');
        $this->addSql('ALTER TABLE sylius_product DROP cms_template_id');
        $this->addSql('DROP INDEX IDX_CFD811CA983BB86C ON sylius_taxon');
        $this->addSql('DROP INDEX IDX_CFD811CAAAB0BDF1 ON sylius_taxon');
        $this->addSql('ALTER TABLE sylius_taxon DROP cms_template_id, DROP product_cms_template_id');
        $this->addSql('DROP TABLE yack_cms_template');
    }
}
