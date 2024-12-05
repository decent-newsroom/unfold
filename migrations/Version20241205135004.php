<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241205135004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Remove duplicate entries
        $this->addSql('DELETE FROM app_user
                        WHERE id NOT IN (
                            SELECT MIN(id)
                            FROM app_user
                            GROUP BY npub
                        );');
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E95FB8BABB ON app_user (npub)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_88BDF3E95FB8BABB');
    }
}
