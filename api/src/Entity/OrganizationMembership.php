<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\State\MembershipProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'organization_membership')]
#[ORM\UniqueConstraint(name: 'unique_user_org', columns: ['user_id', 'organization_id'])]
#[UniqueEntity(fields: ['user', 'organization'], message: 'User is already a member of this organization.')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_USER')", processor: MembershipProcessor::class),
        new Patch(security: "is_granted('ROLE_USER')", processor: MembershipProcessor::class),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['membership:read']],
    denormalizationContext: ['groups' => ['membership:write']],
)]
class OrganizationMembership
{
    public const ROLE_RESIDENT = 'ROLE_RESIDENT';
    public const ROLE_MANAGER = 'ROLE_MANAGER';
    public const ROLE_ADMIN = 'ROLE_ADMIN';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['membership:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'memberships')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['membership:read'])]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Organization::class, inversedBy: 'memberships')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['membership:read', 'membership:write'])]
    private ?Organization $organization = null;

    #[ORM\Column(length: 30)]
    #[Groups(['membership:read', 'membership:write'])]
    private string $role = self::ROLE_RESIDENT;

    #[ORM\Column(length: 20)]
    #[Groups(['membership:read', 'membership:write'])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column]
    #[Groups(['membership:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['membership:read'])]
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

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
