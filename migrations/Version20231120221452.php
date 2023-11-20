<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231120221452 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE recurring_event (id INT AUTO_INCREMENT NOT NULL, image_id INT DEFAULT NULL, start_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, subscription_deadline DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', location VARCHAR(255) NOT NULL, duration VARCHAR(255) NOT NULL COMMENT \'(DC2Type:dateinterval)\', INDEX IDX_51B1C7F83DA5256D (image_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE recurring_event_tag (recurring_event_id INT NOT NULL, tag_id INT NOT NULL, INDEX IDX_7862EE6FE54B259A (recurring_event_id), INDEX IDX_7862EE6FBAD26311 (tag_id), PRIMARY KEY(recurring_event_id, tag_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE recurring_event ADD CONSTRAINT FK_51B1C7F83DA5256D FOREIGN KEY (image_id) REFERENCES image (id)');
        $this->addSql('ALTER TABLE recurring_event_tag ADD CONSTRAINT FK_7862EE6FE54B259A FOREIGN KEY (recurring_event_id) REFERENCES recurring_event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recurring_event_tag ADD CONSTRAINT FK_7862EE6FBAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event ADD recurring_event_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7E54B259A FOREIGN KEY (recurring_event_id) REFERENCES recurring_event (id)');
        $this->addSql('CREATE INDEX IDX_3BAE0AA7E54B259A ON event (recurring_event_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA7E54B259A');
        $this->addSql('ALTER TABLE recurring_event DROP FOREIGN KEY FK_51B1C7F83DA5256D');
        $this->addSql('ALTER TABLE recurring_event_tag DROP FOREIGN KEY FK_7862EE6FE54B259A');
        $this->addSql('ALTER TABLE recurring_event_tag DROP FOREIGN KEY FK_7862EE6FBAD26311');
        $this->addSql('DROP TABLE recurring_event');
        $this->addSql('DROP TABLE recurring_event_tag');
        $this->addSql('DROP INDEX IDX_3BAE0AA7E54B259A ON event');
        $this->addSql('ALTER TABLE event DROP recurring_event_id');
    }
}
