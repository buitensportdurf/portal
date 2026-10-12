<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Reset tokens used to be stored in plain text, valid for 30 days and reusable. They are hashed
 * now; drop the old ones so none of them keep working.
 */
final class Version20261012040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove plain-text password reset tokens';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM reset_password_request');
    }

    public function down(Schema $schema): void
    {
    }
}
