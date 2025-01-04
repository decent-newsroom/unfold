<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241211174012 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_user DROP CONSTRAINT fk_88bdf3e971fd5427');
        $this->addSql('DROP INDEX uniq_88bdf3e971fd5427');
        $this->addSql('ALTER TABLE app_user DROP nzine_bot_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_user ADD nzine_bot_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE app_user ADD CONSTRAINT fk_88bdf3e971fd5427 FOREIGN KEY (nzine_bot_id) REFERENCES nzine_bot (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_88bdf3e971fd5427 ON app_user (nzine_bot_id)');
    }
}
