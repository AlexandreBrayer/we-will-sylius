<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'POC Yack: journal of the PIM/ERP synchronisations (yack_sync_run: source, status, counters; yack_sync_run_line: field-level changes and rejected records)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE yack_sync_run (id INT AUTO_INCREMENT NOT NULL, source VARCHAR(16) NOT NULL, trigger_kind VARCHAR(16) NOT NULL, operator VARCHAR(255) DEFAULT NULL, dry_run TINYINT(1) NOT NULL, replay_of INT DEFAULT NULL, origin VARCHAR(255) NOT NULL, started_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', finished_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', duration_ms INT NOT NULL, status VARCHAR(16) NOT NULL, created INT NOT NULL, updated INT NOT NULL, disabled INT NOT NULL, unchanged INT NOT NULL, rejected INT NOT NULL, error LONGTEXT DEFAULT NULL, notified_to VARCHAR(255) DEFAULT NULL, INDEX idx_yack_sync_run_started_at (started_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE yack_sync_run_line (id INT AUTO_INCREMENT NOT NULL, run_id INT NOT NULL, position INT NOT NULL, code VARCHAR(255) NOT NULL, name VARCHAR(255) DEFAULT NULL, outcome VARCHAR(16) NOT NULL, changes JSON NOT NULL, reason LONGTEXT DEFAULT NULL, payload JSON DEFAULT NULL, file_line INT DEFAULT NULL, INDEX IDX_5C6369D884E3FEC4 (run_id), INDEX idx_yack_sync_run_line_outcome (run_id, outcome), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE yack_sync_run_line ADD CONSTRAINT FK_5C6369D884E3FEC4 FOREIGN KEY (run_id) REFERENCES yack_sync_run (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE yack_sync_run_line');
        $this->addSql('DROP TABLE yack_sync_run');
    }
}
