<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_PLATFORM_ADMIN') or is_granted('ORG_ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_PLATFORM_ADMIN') or is_granted('ORG_ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_PLATFORM_ADMIN') or is_granted('ORG_ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['building:read']],
    denormalizationContext: ['groups' => ['building:write']],
)]
class Building
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['building:read', 'apartment:read', 'connection_request:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Organization::class, inversedBy: 'buildings')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['building:read', 'building:write'])]
    private ?Organization $organization = null;

    #[ORM\Column(length: 500)]
    #[Groups(['building:read', 'building:write', 'apartment:read', 'connection_request:read'])]
    private ?string $address = null;

    #[ORM\Column]
    #[Groups(['building:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): static
    {
        $this->organization = $organization;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
