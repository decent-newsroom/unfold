<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250729102713 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE app_user (id INT AUTO_INCREMENT NOT NULL, npub VARCHAR(255) NOT NULL, roles JSON DEFAULT NULL, UNIQUE INDEX UNIQ_88BDF3E95FB8BABB (npub), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE article (id INT AUTO_INCREMENT NOT NULL, raw JSON DEFAULT NULL, event_id VARCHAR(225) DEFAULT NULL, slug LONGTEXT DEFAULT NULL, content LONGTEXT DEFAULT NULL, kind INT DEFAULT NULL, title LONGTEXT DEFAULT NULL, summary LONGTEXT DEFAULT NULL, pubkey VARCHAR(255) NOT NULL, created_at DATETIME DEFAULT NULL, sig VARCHAR(255) NOT NULL, image LONGTEXT DEFAULT NULL, published_at DATETIME DEFAULT NULL, topics JSON DEFAULT NULL, event_status INT DEFAULT NULL, current_places JSON DEFAULT NULL, rating_negative INT DEFAULT NULL, rating_positive INT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE event (id VARCHAR(225) NOT NULL, event_id VARCHAR(225) DEFAULT NULL, kind INT NOT NULL, pubkey VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, created_at BIGINT NOT NULL, tags JSON NOT NULL, sig VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE nzine (id INT AUTO_INCREMENT NOT NULL, npub VARCHAR(255) NOT NULL, main_categories JSON NOT NULL, lists JSON DEFAULT NULL, editor VARCHAR(255) DEFAULT NULL, slug LONGTEXT DEFAULT NULL, state VARCHAR(255) NOT NULL, nzine_bot_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_65025D9871FD5427 (nzine_bot_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE nzine_bot (id INT AUTO_INCREMENT NOT NULL, encrypted_nsec VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE sessions (sess_id VARBINARY(128) NOT NULL, sess_data LONGBLOB NOT NULL, sess_lifetime INT UNSIGNED NOT NULL, sess_time INT UNSIGNED NOT NULL, INDEX sess_lifetime_idx (sess_lifetime), PRIMARY KEY(sess_id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE nzine ADD CONSTRAINT FK_65025D9871FD5427 FOREIGN KEY (nzine_bot_id) REFERENCES nzine_bot (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE nzine DROP FOREIGN KEY FK_65025D9871FD5427
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE app_user
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE article
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE event
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE nzine
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE nzine_bot
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE sessions
        SQL);
    }
}
