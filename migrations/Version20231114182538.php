<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231114182538 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE event_tag (event_id INT NOT NULL, tag_id INT NOT NULL, INDEX IDX_1246725071F7E88B (event_id), INDEX IDX_12467250BAD26311 (tag_id), PRIMARY KEY(event_id, tag_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tag (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE event_tag ADD CONSTRAINT FK_1246725071F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_tag ADD CONSTRAINT FK_12467250BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event_tag DROP FOREIGN KEY FK_1246725071F7E88B');
        $this->addSql('ALTER TABLE event_tag DROP FOREIGN KEY FK_12467250BAD26311');
        $this->addSql('DROP TABLE event_tag');
        $this->addSql('DROP TABLE tag');
    }
}
