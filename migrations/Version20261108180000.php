<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261108180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'POC Yack: builder level of an administrator (charte, libre_avertissement, libre), charte by default';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_admin_user ADD builder_level VARCHAR(32) DEFAULT \'charte\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_admin_user DROP builder_level');
    }
}
