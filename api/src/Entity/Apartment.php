<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
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
#[ORM\UniqueConstraint(name: 'unique_building_number_type', columns: ['building_id', 'number', 'type'])]
#[UniqueEntity(fields: ['building', 'number', 'type'], message: 'Unit number already exists in this building for this type.')]
#[ApiFilter(SearchFilter::class, properties: ['type' => 'exact', 'building' => 'exact', 'number' => 'partial'])]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_PLATFORM_ADMIN')"),
        new Patch(security: "is_granted('ROLE_PLATFORM_ADMIN')"),
        new Delete(security: "is_granted('ROLE_PLATFORM_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['apartment:read']],
    denormalizationContext: ['groups' => ['apartment:write']],
)]
class Apartment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['apartment:read', 'resident:read', 'connection_request:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Building::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['apartment:read', 'apartment:write', 'resident:read'])]
    private ?Building $building = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Groups(['apartment:read', 'apartment:write', 'resident:read', 'connection_request:read'])]
    private ?string $number = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\Positive]
    #[Groups(['apartment:read', 'apartment:write'])]
    private ?string $totalArea = null;

    #[ORM\Column(length: 20, options: ['default' => 'apartment'])]
    #[Assert\Choice(choices: ['apartment', 'parking'])]
    #[Groups(['apartment:read', 'apartment:write', 'resident:read', 'connection_request:read'])]
    private string $type = 'apartment';

    #[ORM\Column]
    #[Groups(['apartment:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'apartment', targetEntity: Resident::class, cascade: ['remove'])]
    private Collection $residents;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->residents = new ArrayCollection();
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getResidents(): Collection
    {
        return $this->residents;
    }
}
