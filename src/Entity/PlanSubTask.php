<?php

namespace App\Entity;

use App\Repository\PlanSubTaskRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PlanSubTaskRepository::class)]
class PlanSubTask
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PlanTask::class, inversedBy: 'subTaskTemplates')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PlanTask $taskTemplate = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'titleRequired')]
    #[Assert\Length(max: 180)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $documentation = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\NotNull(message: 'difficultyRequired')]
    #[Assert\Range(min: 1, max: 3, notInRangeMessage: 'difficultyRange')]
    private int $difficulty = 2;

    #[ORM\Column]
    private bool $requiresInspection = false;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $position = 0;

    public function __construct()
    {
        $this->position = 0;
        $this->difficulty = 2;
        $this->requiresInspection = false;
    }

    public function __toString(): string
    {
        return $this->title ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTaskTemplate(): ?PlanTask
    {
        return $this->taskTemplate;
    }

    public function setTaskTemplate(?PlanTask $taskTemplate): static
    {
        $this->taskTemplate = $taskTemplate;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

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

    public function getDocumentation(): ?string
    {
        return $this->documentation;
    }

    public function setDocumentation(?string $documentation): static
    {
        $this->documentation = $documentation;

        return $this;
    }

    public function getDifficulty(): int
    {
        return $this->difficulty;
    }

    public function setDifficulty(int $difficulty): static
    {
        $this->difficulty = $difficulty;

        return $this;
    }

    public function requiresInspection(): bool
    {
        return $this->requiresInspection;
    }

    public function setRequiresInspection(bool $requiresInspection): static
    {
        $this->requiresInspection = $requiresInspection;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}

