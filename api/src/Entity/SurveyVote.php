<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use App\State\VoteProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'unique_vote', columns: ['question_id', 'user_id'])]
#[UniqueEntity(fields: ['question', 'user'], message: 'You have already voted on this question.')]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ORG_ROLE_RESIDENT')", processor: VoteProcessor::class),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['vote:read']],
    denormalizationContext: ['groups' => ['vote:write']],
)]
class SurveyVote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['vote:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SurveyQuestion::class, inversedBy: 'votes')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['vote:read', 'vote:write'])]
    private ?SurveyQuestion $question = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['vote:read'])]
    private ?User $user = null;

    #[ORM\Column]
    #[Groups(['vote:read', 'vote:write'])]
    private bool $vote = false;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Groups(['vote:read'])]
    private ?string $weight = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuestion(): ?SurveyQuestion
    {
        return $this->question;
    }

    public function setQuestion(?SurveyQuestion $question): static
    {
        $this->question = $question;
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

    public function isVote(): bool
    {
        return $this->vote;
    }

    public function setVote(bool $vote): static
    {
        $this->vote = $vote;
        return $this;
    }

    public function getWeight(): ?string
    {
        return $this->weight;
    }

    public function setWeight(?string $weight): static
    {
        $this->weight = $weight;
        return $this;
    }
}
