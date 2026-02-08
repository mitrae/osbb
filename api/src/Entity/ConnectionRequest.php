<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use App\State\ConnectionRequestProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ApiFilter(SearchFilter::class, properties: ['status' => 'exact'])]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_USER')", processor: ConnectionRequestProcessor::class),
        new Patch(security: "is_granted('ORG_ROLE_ADMIN')", processor: ConnectionRequestProcessor::class),
    ],
    normalizationContext: ['groups' => ['connection_request:read']],
    denormalizationContext: ['groups' => ['connection_request:write']],
)]
class ConnectionRequest
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['connection_request:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['connection_request:read'])]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['connection_request:read', 'connection_request:write'])]
    private ?Organization $organization = null;

    #[ORM\ManyToOne(targetEntity: Building::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['connection_request:read', 'connection_request:write'])]
    private ?Building $building = null;

    #[ORM\ManyToOne(targetEntity: Apartment::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['connection_request:read', 'connection_request:write'])]
    private ?Apartment $apartment = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    #[Groups(['connection_request:read', 'connection_request:write'])]
    private ?string $fullName = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Groups(['connection_request:read', 'connection_request:write'])]
    private ?string $phone = null;

    #[ORM\Column(length: 20)]
    #[Groups(['connection_request:read', 'connection_request:write'])]
    private string $status = self::STATUS_PENDING;

    #[ORM\ManyToOne(targetEntity: Resident::class)]
    #[Groups(['connection_request:read', 'connection_request:write'])]
    private ?Resident $resident = null;

    #[ORM\Column]
    #[Groups(['connection_request:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['connection_request:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): static
    {
        $this->organization = $organization;
        return $this;
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

    public function getApartment(): ?Apartment
    {
        return $this->apartment;
    }

    public function setApartment(?Apartment $apartment): static
    {
        $this->apartment = $apartment;
        return $this;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = $fullName;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getResident(): ?Resident
    {
        return $this->resident;
    }

    public function setResident(?Resident $resident): static
    {
        $this->resident = $resident;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
