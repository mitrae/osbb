<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260206105447 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE request_comment (id INT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, created_at DATETIME NOT NULL, request_id INT NOT NULL, author_id INT NOT NULL, INDEX IDX_BE50203B427EB8A5 (request_id), INDEX IDX_BE50203BF675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE request_comment ADD CONSTRAINT FK_BE50203B427EB8A5 FOREIGN KEY (request_id) REFERENCES `request` (id)');
        $this->addSql('ALTER TABLE request_comment ADD CONSTRAINT FK_BE50203BF675F31B FOREIGN KEY (author_id) REFERENCES `user` (id)');
        $this->addSql("ALTER TABLE `request` ADD visibility VARCHAR(20) NOT NULL DEFAULT 'private'");
        $this->addSql('ALTER TABLE survey_question ADD description LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE request_comment DROP FOREIGN KEY FK_BE50203B427EB8A5');
        $this->addSql('ALTER TABLE request_comment DROP FOREIGN KEY FK_BE50203BF675F31B');
        $this->addSql('DROP TABLE request_comment');
        $this->addSql('ALTER TABLE `request` DROP visibility');
        $this->addSql('ALTER TABLE survey_question DROP description');
    }
}
