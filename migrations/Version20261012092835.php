<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Per-user switches for the event emails
 */
final class Version20261012092835 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user.disabled_emails';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD disabled_emails JSON DEFAULT \'[]\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP disabled_emails');
    }
}
