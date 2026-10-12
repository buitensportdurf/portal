<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Track when event reminders were sent
 */
final class Version20261012041000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add event.reminder_sent_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event ADD reminder_sent_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event DROP reminder_sent_at');
    }
}
