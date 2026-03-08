<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260308163900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add subscription_open_date to event and recurring_event tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event ADD subscription_open_date DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE recurring_event ADD subscription_open_date DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event DROP COLUMN subscription_open_date');
        $this->addSql('ALTER TABLE recurring_event DROP COLUMN subscription_open_date');
    }
}
