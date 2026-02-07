<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260206172146 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Merge Admin into User, replace ApartmentOwnership with Resident, add ConnectionRequest, simplify OrganizationMembership';
    }

    public function up(Schema $schema): void
    {
        // Create new tables first
        $this->addSql('CREATE TABLE connection_request (id INT AUTO_INCREMENT NOT NULL, full_name VARCHAR(200) NOT NULL, phone VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, organization_id INT NOT NULL, building_id INT NOT NULL, apartment_id INT NOT NULL, resident_id INT DEFAULT NULL, INDEX IDX_409D69BCA76ED395 (user_id), INDEX IDX_409D69BC32C8A3DE (organization_id), INDEX IDX_409D69BC4D2A7E12 (building_id), INDEX IDX_409D69BC176DFE85 (apartment_id), INDEX IDX_409D69BC8012C5B0 (resident_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE resident (id INT AUTO_INCREMENT NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, phone VARCHAR(20) DEFAULT NULL, owned_area NUMERIC(10, 2) NOT NULL, created_at DATETIME NOT NULL, apartment_id INT NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_1D03DA06176DFE85 (apartment_id), INDEX IDX_1D03DA06A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE connection_request ADD CONSTRAINT FK_409D69BCA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE connection_request ADD CONSTRAINT FK_409D69BC32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE connection_request ADD CONSTRAINT FK_409D69BC4D2A7E12 FOREIGN KEY (building_id) REFERENCES building (id)');
        $this->addSql('ALTER TABLE connection_request ADD CONSTRAINT FK_409D69BC176DFE85 FOREIGN KEY (apartment_id) REFERENCES apartment (id)');
        $this->addSql('ALTER TABLE connection_request ADD CONSTRAINT FK_409D69BC8012C5B0 FOREIGN KEY (resident_id) REFERENCES resident (id)');
        $this->addSql('ALTER TABLE resident ADD CONSTRAINT FK_1D03DA06176DFE85 FOREIGN KEY (apartment_id) REFERENCES apartment (id)');
        $this->addSql('ALTER TABLE resident ADD CONSTRAINT FK_1D03DA06A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');

        // Data migration: copy admins to user table with ROLE_PLATFORM_ADMIN
        $this->addSql('INSERT INTO `user` (email, password, first_name, last_name, roles, created_at) SELECT email, password, \'Admin\', \'User\', \'["ROLE_PLATFORM_ADMIN","ROLE_USER"]\', created_at FROM admin WHERE email NOT IN (SELECT email FROM `user`)');
        // For admins whose email already exists in user table, add ROLE_PLATFORM_ADMIN
        $this->addSql('UPDATE `user` SET roles = JSON_ARRAY_APPEND(roles, \'$\', \'ROLE_PLATFORM_ADMIN\') WHERE email IN (SELECT email FROM admin)');

        // Data migration: copy ApartmentOwnership to Resident
        $this->addSql('INSERT INTO resident (first_name, last_name, phone, apartment_id, owned_area, user_id, created_at) SELECT u.first_name, u.last_name, u.phone, ao.apartment_id, ao.owned_area, ao.user_id, ao.created_at FROM apartment_ownership ao JOIN `user` u ON ao.user_id = u.id');

        // Drop old tables
        $this->addSql('ALTER TABLE apartment_ownership DROP FOREIGN KEY `FK_9E4EDF1C176DFE85`');
        $this->addSql('ALTER TABLE apartment_ownership DROP FOREIGN KEY `FK_9E4EDF1CA76ED395`');
        $this->addSql('DROP TABLE admin');
        $this->addSql('DROP TABLE apartment_ownership');

        // Add city to organization
        $this->addSql('ALTER TABLE organization ADD city VARCHAR(255) DEFAULT NULL');

        // Simplify organization_membership: remove status and updated_at, delete ROLE_RESIDENT memberships
        $this->addSql('DELETE FROM organization_membership WHERE role = \'ROLE_RESIDENT\'');
        $this->addSql('ALTER TABLE organization_membership DROP status, DROP updated_at');

        // Remove legacy fields from user
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY `FK_8D93D64932C8A3DE`');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY `FK_8D93D6494D2A7E12`');
        $this->addSql('DROP INDEX IDX_8D93D64932C8A3DE ON `user`');
        $this->addSql('DROP INDEX IDX_8D93D6494D2A7E12 ON `user`');
        $this->addSql('ALTER TABLE `user` DROP apartment, DROP organization_id, DROP building_id');

        $this->addSql('ALTER TABLE `request` CHANGE visibility visibility VARCHAR(20) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE admin (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, password VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, roles JSON NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_880E0D76E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE apartment_ownership (id INT AUTO_INCREMENT NOT NULL, owned_area NUMERIC(10, 2) NOT NULL, created_at DATETIME NOT NULL, apartment_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_9E4EDF1CA76ED395 (user_id), INDEX IDX_9E4EDF1C176DFE85 (apartment_id), UNIQUE INDEX unique_apartment_user (apartment_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE apartment_ownership ADD CONSTRAINT `FK_9E4EDF1C176DFE85` FOREIGN KEY (apartment_id) REFERENCES apartment (id)');
        $this->addSql('ALTER TABLE apartment_ownership ADD CONSTRAINT `FK_9E4EDF1CA76ED395` FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE connection_request DROP FOREIGN KEY FK_409D69BCA76ED395');
        $this->addSql('ALTER TABLE connection_request DROP FOREIGN KEY FK_409D69BC32C8A3DE');
        $this->addSql('ALTER TABLE connection_request DROP FOREIGN KEY FK_409D69BC4D2A7E12');
        $this->addSql('ALTER TABLE connection_request DROP FOREIGN KEY FK_409D69BC176DFE85');
        $this->addSql('ALTER TABLE connection_request DROP FOREIGN KEY FK_409D69BC8012C5B0');
        $this->addSql('ALTER TABLE resident DROP FOREIGN KEY FK_1D03DA06176DFE85');
        $this->addSql('ALTER TABLE resident DROP FOREIGN KEY FK_1D03DA06A76ED395');
        $this->addSql('DROP TABLE connection_request');
        $this->addSql('DROP TABLE resident');
        $this->addSql('ALTER TABLE organization DROP city');
        $this->addSql('ALTER TABLE organization_membership ADD status VARCHAR(20) NOT NULL, ADD updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `request` CHANGE visibility visibility VARCHAR(20) DEFAULT \'private\' NOT NULL');
        $this->addSql('ALTER TABLE `user` ADD apartment VARCHAR(20) DEFAULT NULL, ADD organization_id INT DEFAULT NULL, ADD building_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT `FK_8D93D64932C8A3DE` FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT `FK_8D93D6494D2A7E12` FOREIGN KEY (building_id) REFERENCES building (id)');
        $this->addSql('CREATE INDEX IDX_8D93D64932C8A3DE ON `user` (organization_id)');
        $this->addSql('CREATE INDEX IDX_8D93D6494D2A7E12 ON `user` (building_id)');
    }
}
