<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250913130133 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE question (id INT AUTO_INCREMENT NOT NULL, event_id INT NOT NULL, question VARCHAR(1023) NOT NULL, required TINYINT(1) NOT NULL, type VARCHAR(255) NOT NULL, INDEX IDX_B6F7494E71F7E88B (event_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE question_answer (id INT AUTO_INCREMENT NOT NULL, question_id INT NOT NULL, subscription_id INT NOT NULL, answer VARCHAR(1023) NOT NULL, INDEX IDX_DD80652D1E27F6BF (question_id), INDEX IDX_DD80652D9A1887DC (subscription_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT FK_B6F7494E71F7E88B FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE question_answer ADD CONSTRAINT FK_DD80652D1E27F6BF FOREIGN KEY (question_id) REFERENCES question (id)');
        $this->addSql('ALTER TABLE question_answer ADD CONSTRAINT FK_DD80652D9A1887DC FOREIGN KEY (subscription_id) REFERENCES event_subscription (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE question DROP FOREIGN KEY FK_B6F7494E71F7E88B');
        $this->addSql('ALTER TABLE question_answer DROP FOREIGN KEY FK_DD80652D1E27F6BF');
        $this->addSql('ALTER TABLE question_answer DROP FOREIGN KEY FK_DD80652D9A1887DC');
        $this->addSql('DROP TABLE question');
        $this->addSql('DROP TABLE question_answer');
    }
}
