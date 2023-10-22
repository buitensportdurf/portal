<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231022143718 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE event (id INT AUTO_INCREMENT NOT NULL, start_date DATETIME NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, subscription_deadline DATETIME NOT NULL, location VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE event_subscription (id INT AUTO_INCREMENT NOT NULL, event_id INT NOT NULL, created_user_id INT NOT NULL, amount INT NOT NULL, created_date DATETIME NOT NULL, INDEX IDX_4ED56E2071F7E88B (event_id), INDEX IDX_4ED56E20E104C1D3 (created_user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE event_subscription ADD CONSTRAINT FK_4ED56E2071F7E88B FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE event_subscription ADD CONSTRAINT FK_4ED56E20E104C1D3 FOREIGN KEY (created_user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event_subscription DROP FOREIGN KEY FK_4ED56E2071F7E88B');
        $this->addSql('ALTER TABLE event_subscription DROP FOREIGN KEY FK_4ED56E20E104C1D3');
        $this->addSql('DROP TABLE event');
        $this->addSql('DROP TABLE event_subscription');
    }
}
