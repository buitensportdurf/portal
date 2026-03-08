<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260308174759 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event ADD published TINYINT NOT NULL DEFAULT 1');
        $this->addSql('UPDATE event SET published = NOT draft');
        $this->addSql('ALTER TABLE event DROP draft');
        $this->addSql('ALTER TABLE recurring_event ADD published TINYINT NOT NULL DEFAULT 1');
        $this->addSql('UPDATE recurring_event SET published = NOT draft');
        $this->addSql('ALTER TABLE recurring_event DROP draft');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event ADD draft TINYINT NOT NULL DEFAULT 0');
        $this->addSql('UPDATE event SET draft = NOT published');
        $this->addSql('ALTER TABLE event DROP published');
        $this->addSql('ALTER TABLE recurring_event ADD draft TINYINT NOT NULL DEFAULT 0');
        $this->addSql('UPDATE recurring_event SET draft = NOT published');
        $this->addSql('ALTER TABLE recurring_event DROP published');
    }
}
