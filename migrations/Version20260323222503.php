<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260323222503 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace duration (DateInterval) with end_date (datetime) on event and recurring_event';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event ADD end_date DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE recurring_event ADD end_date DATETIME DEFAULT NULL');

        foreach (['event', 'recurring_event'] as $table) {
            $rows = $this->connection->fetchAllAssociative("SELECT id, start_date, duration FROM {$table}");
            foreach ($rows as $row) {
                $start = new \DateTimeImmutable($row['start_date']);
                $end = $start->add(new \DateInterval(ltrim($row['duration'], '+-')));
                $this->addSql("UPDATE {$table} SET end_date = ? WHERE id = ?", [$end->format('Y-m-d H:i:s'), $row['id']]);
            }
        }

        $this->addSql('UPDATE event SET end_date = start_date WHERE end_date IS NULL');
        $this->addSql('UPDATE recurring_event SET end_date = start_date WHERE end_date IS NULL');

        $this->addSql('ALTER TABLE event CHANGE end_date end_date DATETIME NOT NULL');
        $this->addSql('ALTER TABLE recurring_event CHANGE end_date end_date DATETIME NOT NULL');
        $this->addSql('ALTER TABLE event DROP duration');
        $this->addSql('ALTER TABLE recurring_event DROP duration');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event ADD duration VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE recurring_event ADD duration VARCHAR(255) DEFAULT NULL');

        foreach (['event', 'recurring_event'] as $table) {
            $rows = $this->connection->fetchAllAssociative("SELECT id, start_date, end_date FROM {$table}");
            foreach ($rows as $row) {
                $start = new \DateTimeImmutable($row['start_date']);
                $end = new \DateTimeImmutable($row['end_date']);
                $interval = $start->diff($end);
                $this->addSql("UPDATE {$table} SET duration = ? WHERE id = ?", [$interval->format('P%dDT%hH%iM%sS'), $row['id']]);
            }
        }

        $this->addSql('ALTER TABLE event CHANGE duration duration VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE recurring_event CHANGE duration duration VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE event DROP end_date');
        $this->addSql('ALTER TABLE recurring_event DROP end_date');
    }
}
