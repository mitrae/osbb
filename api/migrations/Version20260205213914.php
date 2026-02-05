<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260205213914 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE building (id INT AUTO_INCREMENT NOT NULL, address VARCHAR(500) NOT NULL, created_at DATETIME NOT NULL, organization_id INT NOT NULL, INDEX IDX_E16F61D432C8A3DE (organization_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE organization (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, address VARCHAR(500) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE `request` (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, author_id INT NOT NULL, assignee_id INT DEFAULT NULL, organization_id INT NOT NULL, INDEX IDX_3B978F9FF675F31B (author_id), INDEX IDX_3B978F9F59EC7D60 (assignee_id), INDEX IDX_3B978F9F32C8A3DE (organization_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE survey (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, is_active TINYINT NOT NULL, start_date DATETIME DEFAULT NULL, end_date DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, organization_id INT NOT NULL, created_by_id INT NOT NULL, INDEX IDX_AD5F9BFC32C8A3DE (organization_id), INDEX IDX_AD5F9BFCB03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE survey_question (id INT AUTO_INCREMENT NOT NULL, question_text VARCHAR(500) NOT NULL, survey_id INT NOT NULL, INDEX IDX_EA000F69B3FE509D (survey_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE survey_vote (id INT AUTO_INCREMENT NOT NULL, vote TINYINT NOT NULL, question_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_9CF985AF1E27F6BF (question_id), INDEX IDX_9CF985AFA76ED395 (user_id), UNIQUE INDEX unique_vote (question_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, phone VARCHAR(20) DEFAULT NULL, roles JSON NOT NULL, apartment VARCHAR(20) DEFAULT NULL, created_at DATETIME NOT NULL, organization_id INT DEFAULT NULL, building_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), INDEX IDX_8D93D64932C8A3DE (organization_id), INDEX IDX_8D93D6494D2A7E12 (building_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE building ADD CONSTRAINT FK_E16F61D432C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE `request` ADD CONSTRAINT FK_3B978F9FF675F31B FOREIGN KEY (author_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE `request` ADD CONSTRAINT FK_3B978F9F59EC7D60 FOREIGN KEY (assignee_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE `request` ADD CONSTRAINT FK_3B978F9F32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE survey ADD CONSTRAINT FK_AD5F9BFC32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE survey ADD CONSTRAINT FK_AD5F9BFCB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE survey_question ADD CONSTRAINT FK_EA000F69B3FE509D FOREIGN KEY (survey_id) REFERENCES survey (id)');
        $this->addSql('ALTER TABLE survey_vote ADD CONSTRAINT FK_9CF985AF1E27F6BF FOREIGN KEY (question_id) REFERENCES survey_question (id)');
        $this->addSql('ALTER TABLE survey_vote ADD CONSTRAINT FK_9CF985AFA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT FK_8D93D64932C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT FK_8D93D6494D2A7E12 FOREIGN KEY (building_id) REFERENCES building (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE building DROP FOREIGN KEY FK_E16F61D432C8A3DE');
        $this->addSql('ALTER TABLE `request` DROP FOREIGN KEY FK_3B978F9FF675F31B');
        $this->addSql('ALTER TABLE `request` DROP FOREIGN KEY FK_3B978F9F59EC7D60');
        $this->addSql('ALTER TABLE `request` DROP FOREIGN KEY FK_3B978F9F32C8A3DE');
        $this->addSql('ALTER TABLE survey DROP FOREIGN KEY FK_AD5F9BFC32C8A3DE');
        $this->addSql('ALTER TABLE survey DROP FOREIGN KEY FK_AD5F9BFCB03A8386');
        $this->addSql('ALTER TABLE survey_question DROP FOREIGN KEY FK_EA000F69B3FE509D');
        $this->addSql('ALTER TABLE survey_vote DROP FOREIGN KEY FK_9CF985AF1E27F6BF');
        $this->addSql('ALTER TABLE survey_vote DROP FOREIGN KEY FK_9CF985AFA76ED395');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D64932C8A3DE');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D6494D2A7E12');
        $this->addSql('DROP TABLE building');
        $this->addSql('DROP TABLE organization');
        $this->addSql('DROP TABLE `request`');
        $this->addSql('DROP TABLE survey');
        $this->addSql('DROP TABLE survey_question');
        $this->addSql('DROP TABLE survey_vote');
        $this->addSql('DROP TABLE `user`');
    }
}
