<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * POC Yack — SAV : modèles (ServiceCode), vues éclatées, repères pièces et numéros de série.
 */
final class Version20261102094834 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Yack after-sales: models, exploded views, exploded view items, serial numbers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE yack_exploded_view (id INT AUTO_INCREMENT NOT NULL, model_id INT NOT NULL, name VARCHAR(255) NOT NULL, image_path VARCHAR(255) NOT NULL, position INT NOT NULL, INDEX IDX_A3E6B05E7975B7E7 (model_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE yack_exploded_view_item (id INT AUTO_INCREMENT NOT NULL, exploded_view_id INT NOT NULL, part_id INT NOT NULL, marker INT NOT NULL, x DOUBLE PRECISION NOT NULL, y DOUBLE PRECISION NOT NULL, quantity INT NOT NULL, INDEX IDX_F02A290D86A5F283 (exploded_view_id), INDEX IDX_F02A290D4CE34BEC (part_id), UNIQUE INDEX yack_exploded_view_item_marker (exploded_view_id, marker), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE yack_model (id INT AUTO_INCREMENT NOT NULL, finished_product_id INT DEFAULT NULL, service_code VARCHAR(32) NOT NULL, name VARCHAR(255) NOT NULL, family VARCHAR(128) NOT NULL, UNIQUE INDEX UNIQ_9974D362B9568474 (service_code), INDEX IDX_9974D362380E9C02 (finished_product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE yack_serial_number (id INT AUTO_INCREMENT NOT NULL, serial VARCHAR(64) NOT NULL, UNIQUE INDEX UNIQ_5D0FB2C6D374C9DC (serial), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE yack_serial_number_model (serial_number_id INT NOT NULL, model_id INT NOT NULL, INDEX IDX_CC1C952D9E45236B (serial_number_id), INDEX IDX_CC1C952D7975B7E7 (model_id), PRIMARY KEY(serial_number_id, model_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE yack_exploded_view ADD CONSTRAINT FK_A3E6B05E7975B7E7 FOREIGN KEY (model_id) REFERENCES yack_model (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE yack_exploded_view_item ADD CONSTRAINT FK_F02A290D86A5F283 FOREIGN KEY (exploded_view_id) REFERENCES yack_exploded_view (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE yack_exploded_view_item ADD CONSTRAINT FK_F02A290D4CE34BEC FOREIGN KEY (part_id) REFERENCES sylius_product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE yack_model ADD CONSTRAINT FK_9974D362380E9C02 FOREIGN KEY (finished_product_id) REFERENCES sylius_product (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE yack_serial_number_model ADD CONSTRAINT FK_CC1C952D9E45236B FOREIGN KEY (serial_number_id) REFERENCES yack_serial_number (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE yack_serial_number_model ADD CONSTRAINT FK_CC1C952D7975B7E7 FOREIGN KEY (model_id) REFERENCES yack_model (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yack_exploded_view DROP FOREIGN KEY FK_A3E6B05E7975B7E7');
        $this->addSql('ALTER TABLE yack_exploded_view_item DROP FOREIGN KEY FK_F02A290D86A5F283');
        $this->addSql('ALTER TABLE yack_exploded_view_item DROP FOREIGN KEY FK_F02A290D4CE34BEC');
        $this->addSql('ALTER TABLE yack_model DROP FOREIGN KEY FK_9974D362380E9C02');
        $this->addSql('ALTER TABLE yack_serial_number_model DROP FOREIGN KEY FK_CC1C952D9E45236B');
        $this->addSql('ALTER TABLE yack_serial_number_model DROP FOREIGN KEY FK_CC1C952D7975B7E7');
        $this->addSql('DROP TABLE yack_exploded_view');
        $this->addSql('DROP TABLE yack_exploded_view_item');
        $this->addSql('DROP TABLE yack_model');
        $this->addSql('DROP TABLE yack_serial_number');
        $this->addSql('DROP TABLE yack_serial_number_model');
    }
}
