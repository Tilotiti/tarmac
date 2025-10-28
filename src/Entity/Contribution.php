<?php

namespace App\Entity;

use App\Repository\ContributionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContributionRepository::class)]
#[ORM\Table(name: 'contribution')]
#[ORM\UniqueConstraint(name: 'UNIQ_CONTRIBUTION', columns: ['sub_task_id', 'membership_id'])]
#[ORM\Index(columns: ['created_at'], name: 'idx_contribution_created_at')]
class Contribution
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SubTask::class, inversedBy: 'contributions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?SubTask $subTask = null;

    #[ORM\ManyToOne(targetEntity: Membership::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Membership $membership = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotNull(message: 'timeSpentRequired')]
    #[Assert\Positive(message: 'timeSpentMustBePositive')]
    private ?float $timeSpent = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubTask(): ?SubTask
    {
        return $this->subTask;
    }

    public function setSubTask(?SubTask $subTask): static
    {
        $this->subTask = $subTask;

        return $this;
    }

    public function getMembership(): ?Membership
    {
        return $this->membership;
    }

    public function setMembership(?Membership $membership): static
    {
        $this->membership = $membership;

        return $this;
    }

    public function getTimeSpent(): ?float
    {
        return $this->timeSpent;
    }

    public function setTimeSpent(float $timeSpent): static
    {
        $this->timeSpent = $timeSpent;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}

