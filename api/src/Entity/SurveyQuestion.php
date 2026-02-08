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
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ApiFilter(SearchFilter::class, properties: ['survey' => 'exact'])]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ORG_ROLE_ADMIN')"),
        new Patch(security: "is_granted('ORG_ROLE_ADMIN')"),
        new Delete(security: "is_granted('ORG_ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['question:read']],
    denormalizationContext: ['groups' => ['question:write']],
)]
class SurveyQuestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['question:read', 'survey:read', 'vote:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Survey::class, inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['question:read', 'question:write'])]
    private ?Survey $survey = null;

    #[ORM\Column(length: 500)]
    #[Assert\NotBlank]
    #[Groups(['question:read', 'question:write', 'survey:read'])]
    private ?string $questionText = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['question:read', 'question:write', 'survey:read'])]
    private ?string $description = null;

    #[ORM\OneToMany(mappedBy: 'question', targetEntity: SurveyVote::class, cascade: ['remove'])]
    private Collection $votes;

    public function __construct()
    {
        $this->votes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSurvey(): ?Survey
    {
        return $this->survey;
    }

    public function setSurvey(?Survey $survey): static
    {
        $this->survey = $survey;
        return $this;
    }

    public function getQuestionText(): ?string
    {
        return $this->questionText;
    }

    public function setQuestionText(string $questionText): static
    {
        $this->questionText = $questionText;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getVotes(): Collection
    {
        return $this->votes;
    }
}
