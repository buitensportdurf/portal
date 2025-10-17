<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251017203557 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event CHANGE start_date start_date DATETIME NOT NULL, CHANGE subscription_deadline subscription_deadline DATETIME DEFAULT NULL, CHANGE duration duration VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE event_subscription CHANGE created_user_id created_user_id BINARY(16) NOT NULL');
        $this->addSql('ALTER TABLE `group` CHANGE roles roles JSON NOT NULL');
        $this->addSql('ALTER TABLE recurring_event CHANGE start_date start_date DATETIME NOT NULL, CHANGE subscription_deadline subscription_deadline DATETIME DEFAULT NULL, CHANGE duration duration VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE reset_password_request CHANGE user_id user_id BINARY(16) NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE id id BINARY(16) NOT NULL, CHANGE roles roles JSON NOT NULL');
        $this->addSql('ALTER TABLE user_group CHANGE user_id user_id BINARY(16) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event CHANGE start_date start_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE subscription_deadline subscription_deadline DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE duration duration VARCHAR(255) NOT NULL COMMENT \'(DC2Type:dateinterval)\'');
        $this->addSql('ALTER TABLE event_subscription CHANGE created_user_id created_user_id BINARY(16) NOT NULL COMMENT \'(DC2Type:ulid)\'');
        $this->addSql('ALTER TABLE `group` CHANGE roles roles JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE recurring_event CHANGE start_date start_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE subscription_deadline subscription_deadline DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE duration duration VARCHAR(255) NOT NULL COMMENT \'(DC2Type:dateinterval)\'');
        $this->addSql('ALTER TABLE reset_password_request CHANGE user_id user_id BINARY(16) NOT NULL COMMENT \'(DC2Type:ulid)\'');
        $this->addSql('ALTER TABLE user CHANGE id id BINARY(16) NOT NULL COMMENT \'(DC2Type:ulid)\', CHANGE roles roles JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE user_group CHANGE user_id user_id BINARY(16) NOT NULL COMMENT \'(DC2Type:ulid)\'');
    }
}
