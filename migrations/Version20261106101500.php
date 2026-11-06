<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106101500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'POC Yack: shipping fees taxed at the product VAT rate (tax category "tva_normale" on the Yack shipping methods)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE sylius_shipping_method sm
            INNER JOIN sylius_tax_category tc ON tc.code = 'tva_normale'
            SET sm.tax_category_id = tc.id
            WHERE sm.code IN ('yack_transport', 'yack_agence') AND sm.tax_category_id IS NULL
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE sylius_shipping_method sm
            INNER JOIN sylius_tax_category tc ON tc.code = 'tva_normale' AND tc.id = sm.tax_category_id
            SET sm.tax_category_id = NULL
            WHERE sm.code IN ('yack_transport', 'yack_agence')
            SQL);
    }
}
