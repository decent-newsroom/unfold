<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250302163932 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_user DROP name');
        $this->addSql('ALTER TABLE app_user DROP display_name');
        $this->addSql('ALTER TABLE app_user DROP about');
        $this->addSql('ALTER TABLE app_user DROP website');
        $this->addSql('ALTER TABLE app_user DROP picture');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_user ADD name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE app_user ADD display_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE app_user ADD about TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE app_user ADD website TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE app_user ADD picture TEXT DEFAULT NULL');
    }
}
