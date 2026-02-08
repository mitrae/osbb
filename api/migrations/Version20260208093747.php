<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208093747 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE survey SET start_date = created_at WHERE start_date IS NULL');
        $this->addSql('UPDATE survey SET end_date = DATE_ADD(created_at, INTERVAL 30 DAY) WHERE end_date IS NULL');
        $this->addSql('ALTER TABLE survey CHANGE start_date start_date DATETIME NOT NULL, CHANGE end_date end_date DATETIME NOT NULL');
        $this->addSql('DROP INDEX unique_vote ON survey_vote');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE survey CHANGE start_date start_date DATETIME DEFAULT NULL, CHANGE end_date end_date DATETIME DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX unique_vote ON survey_vote (question_id, user_id)');
    }
}
