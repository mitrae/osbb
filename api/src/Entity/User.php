<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\State\UserPasswordHasher;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: '`user`')]
#[UniqueEntity('email')]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_MANAGER')"),
        new Get(security: "is_granted('ROLE_USER') and object == user"),
        new Post(security: "is_granted('ROLE_ADMIN')", processor: UserPasswordHasher::class),
        new Patch(security: "is_granted('ROLE_ADMIN') or object == user", processor: UserPasswordHasher::class),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['user:read']],
    denormalizationContext: ['groups' => ['user:write']],
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read', 'request:read', 'survey:read', 'vote:read', 'membership:read', 'comment:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\Email]
    #[Assert\NotBlank]
    #[Groups(['user:read', 'user:write', 'request:read', 'membership:read', 'comment:read'])]
    private ?string $email = null;

    #[ORM\Column]
    private ?string $password = null;

    #[Groups(['user:write'])]
    private ?string $plainPassword = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Groups(['user:read', 'user:write', 'request:read', 'survey:read', 'membership:read', 'comment:read'])]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Groups(['user:read', 'user:write', 'request:read', 'survey:read', 'membership:read', 'comment:read'])]
    private ?string $lastName = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    private ?string $phone = null;

    #[ORM\Column(type: 'json')]
    #[Groups(['user:read'])]
    private array $roles = ['ROLE_USER'];

    #[ORM\ManyToOne(targetEntity: Organization::class, inversedBy: 'users')]
    #[Groups(['user:read', 'user:write'])]
    private ?Organization $organization = null;

    #[ORM\ManyToOne(targetEntity: Building::class, inversedBy: 'users')]
    #[Groups(['user:read', 'user:write'])]
    private ?Building $building = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    private ?string $apartment = null;

    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: OrganizationMembership::class)]
    private Collection $memberships;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->memberships = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return 'user:' . $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;
        return $this;
    }

    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
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

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
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

    public function getApartment(): ?string
    {
        return $this->apartment;
    }

    public function setApartment(?string $apartment): static
    {
        $this->apartment = $apartment;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getMemberships(): Collection
    {
        return $this->memberships;
    }
}
