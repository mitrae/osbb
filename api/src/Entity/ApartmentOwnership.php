<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'unique_apartment_user', columns: ['apartment_id', 'user_id'])]
#[UniqueEntity(fields: ['apartment', 'user'], message: 'This ownership already exists.')]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ORG_ROLE_ADMIN')"),
        new Patch(security: "is_granted('ORG_ROLE_ADMIN')"),
        new Delete(security: "is_granted('ORG_ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['ownership:read']],
    denormalizationContext: ['groups' => ['ownership:write']],
)]
class ApartmentOwnership
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['ownership:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Apartment::class, inversedBy: 'ownerships')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['ownership:read', 'ownership:write'])]
    private ?Apartment $apartment = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['ownership:read', 'ownership:write'])]
    private ?User $user = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\Positive]
    #[Groups(['ownership:read', 'ownership:write'])]
    private ?string $ownedArea = null;

    #[ORM\Column]
    #[Groups(['ownership:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getApartment(): ?Apartment
    {
        return $this->apartment;
    }

    public function setApartment(?Apartment $apartment): static
    {
        $this->apartment = $apartment;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getOwnedArea(): ?string
    {
        return $this->ownedArea;
    }

    public function setOwnedArea(string $ownedArea): static
    {
        $this->ownedArea = $ownedArea;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
