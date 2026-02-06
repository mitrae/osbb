<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260206082043 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE organization_membership (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(30) NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, organization_id INT NOT NULL, INDEX IDX_6FA4406AA76ED395 (user_id), INDEX IDX_6FA4406A32C8A3DE (organization_id), UNIQUE INDEX unique_user_org (user_id, organization_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE organization_membership ADD CONSTRAINT FK_6FA4406AA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE organization_membership ADD CONSTRAINT FK_6FA4406A32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');

        // Data migration: copy existing User.organization_id into memberships
        $this->addSql("INSERT INTO organization_membership (user_id, organization_id, role, status, created_at, updated_at) SELECT id, organization_id, 'ROLE_RESIDENT', 'approved', NOW(), NOW() FROM `user` WHERE organization_id IS NOT NULL");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE organization_membership DROP FOREIGN KEY FK_6FA4406AA76ED395');
        $this->addSql('ALTER TABLE organization_membership DROP FOREIGN KEY FK_6FA4406A32C8A3DE');
        $this->addSql('DROP TABLE organization_membership');
    }
}
