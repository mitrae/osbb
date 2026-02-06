<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_PLATFORM_ADMIN')"),
        new Patch(security: "is_granted('ROLE_PLATFORM_ADMIN') or is_granted('ORG_ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_PLATFORM_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['organization:read']],
    denormalizationContext: ['groups' => ['organization:write']],
)]
class Organization
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['organization:read', 'building:read', 'user:read', 'request:read', 'survey:read', 'membership:read', 'connection_request:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['organization:read', 'organization:write', 'building:read', 'user:read', 'request:read', 'survey:read', 'membership:read', 'connection_request:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['organization:read', 'organization:write', 'membership:read'])]
    private ?string $city = null;

    #[ORM\Column(length: 500)]
    #[Groups(['organization:read', 'organization:write', 'membership:read'])]
    private ?string $address = null;

    #[ORM\Column]
    #[Groups(['organization:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'organization', targetEntity: Building::class)]
    private Collection $buildings;

    #[ORM\OneToMany(mappedBy: 'organization', targetEntity: OrganizationMembership::class)]
    private Collection $memberships;

    public function __construct()
    {
        $this->buildings = new ArrayCollection();
        $this->memberships = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;
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

    public function getBuildings(): Collection
    {
        return $this->buildings;
    }

    public function getMemberships(): Collection
    {
        return $this->memberships;
    }
}
