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
use App\State\ResidentProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ApiFilter(SearchFilter::class, properties: ['user' => 'exact', 'apartment' => 'exact'])]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_PLATFORM_ADMIN')", processor: ResidentProcessor::class),
        new Patch(security: "is_granted('ROLE_PLATFORM_ADMIN') or is_granted('ORG_ROLE_ADMIN')", processor: ResidentProcessor::class),
        new Delete(security: "is_granted('ROLE_PLATFORM_ADMIN')", processor: ResidentProcessor::class),
    ],
    normalizationContext: ['groups' => ['resident:read']],
    denormalizationContext: ['groups' => ['resident:write']],
)]
class Resident
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['resident:read', 'connection_request:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Groups(['resident:read', 'resident:write'])]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Groups(['resident:read', 'resident:write'])]
    private ?string $lastName = null;

    #[ORM\ManyToOne(targetEntity: Apartment::class, inversedBy: 'residents')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['resident:read', 'resident:write'])]
    private ?Apartment $apartment = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\Positive]
    #[Groups(['resident:read', 'resident:write'])]
    private ?string $ownedArea = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[Groups(['resident:read', 'resident:write'])]
    private ?User $user = null;

    #[ORM\Column]
    #[Groups(['resident:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
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

    public function getOwnedArea(): ?string
    {
        return $this->ownedArea;
    }

    public function setOwnedArea(string $ownedArea): static
    {
        $this->ownedArea = $ownedArea;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
