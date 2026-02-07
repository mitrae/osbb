<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260207112958 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX unique_building_number ON apartment');
        $this->addSql('ALTER TABLE apartment ADD type VARCHAR(20) DEFAULT \'apartment\' NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX unique_building_number_type ON apartment (building_id, number, type)');
        $this->addSql('ALTER TABLE survey ADD property_type VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX unique_building_number_type ON apartment');
        $this->addSql('ALTER TABLE apartment DROP type');
        $this->addSql('CREATE UNIQUE INDEX unique_building_number ON apartment (building_id, number)');
        $this->addSql('ALTER TABLE survey DROP property_type');
    }
}
