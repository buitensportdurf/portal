<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The language people use the site and get emails in
 */
final class Version20261012114005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user.locale';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD locale VARCHAR(5) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP locale');
    }
}
