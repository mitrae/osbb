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
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'unique_building_number', columns: ['building_id', 'number'])]
#[UniqueEntity(fields: ['building', 'number'], message: 'Apartment number already exists in this building.')]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ORG_ROLE_ADMIN')"),
        new Patch(security: "is_granted('ORG_ROLE_ADMIN')"),
        new Delete(security: "is_granted('ORG_ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['apartment:read']],
    denormalizationContext: ['groups' => ['apartment:write']],
)]
class Apartment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['apartment:read', 'ownership:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Building::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['apartment:read', 'apartment:write'])]
    private ?Building $building = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Groups(['apartment:read', 'apartment:write', 'ownership:read'])]
    private ?string $number = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\Positive]
    #[Groups(['apartment:read', 'apartment:write'])]
    private ?string $totalArea = null;

    #[ORM\Column]
    #[Groups(['apartment:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'apartment', targetEntity: ApartmentOwnership::class, cascade: ['remove'])]
    private Collection $ownerships;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->ownerships = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBuilding(): ?Building
    {
        return $this->building;
    }

    public function setBuilding(?Building $building): static
    {
        $this->building = $building;
        return $this;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;
        return $this;
    }

    public function getTotalArea(): ?string
    {
        return $this->totalArea;
    }

    public function setTotalArea(string $totalArea): static
    {
        $this->totalArea = $totalArea;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getOwnerships(): Collection
    {
        return $this->ownerships;
    }
}
