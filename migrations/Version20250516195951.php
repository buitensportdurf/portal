<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250516195951 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE event ADD guests_allowed TINYINT(1) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE recurring_event ADD guests_allowed TINYINT(1) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE event SET guests_allowed = 0
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE recurring_event SET guests_allowed = 0
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE recurring_event DROP guests_allowed
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event DROP guests_allowed
        SQL);
    }
}
