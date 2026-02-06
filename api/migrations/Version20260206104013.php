<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260206104013 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE apartment (id INT AUTO_INCREMENT NOT NULL, number VARCHAR(20) NOT NULL, total_area NUMERIC(10, 2) NOT NULL, created_at DATETIME NOT NULL, building_id INT NOT NULL, INDEX IDX_4D7E68544D2A7E12 (building_id), UNIQUE INDEX unique_building_number (building_id, number), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE apartment_ownership (id INT AUTO_INCREMENT NOT NULL, owned_area NUMERIC(10, 2) NOT NULL, created_at DATETIME NOT NULL, apartment_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_9E4EDF1C176DFE85 (apartment_id), INDEX IDX_9E4EDF1CA76ED395 (user_id), UNIQUE INDEX unique_apartment_user (apartment_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE apartment ADD CONSTRAINT FK_4D7E68544D2A7E12 FOREIGN KEY (building_id) REFERENCES building (id)');
        $this->addSql('ALTER TABLE apartment_ownership ADD CONSTRAINT FK_9E4EDF1C176DFE85 FOREIGN KEY (apartment_id) REFERENCES apartment (id)');
        $this->addSql('ALTER TABLE apartment_ownership ADD CONSTRAINT FK_9E4EDF1CA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE survey_vote ADD weight NUMERIC(10, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE apartment DROP FOREIGN KEY FK_4D7E68544D2A7E12');
        $this->addSql('ALTER TABLE apartment_ownership DROP FOREIGN KEY FK_9E4EDF1C176DFE85');
        $this->addSql('ALTER TABLE apartment_ownership DROP FOREIGN KEY FK_9E4EDF1CA76ED395');
        $this->addSql('DROP TABLE apartment');
        $this->addSql('DROP TABLE apartment_ownership');
        $this->addSql('ALTER TABLE survey_vote DROP weight');
    }
}
