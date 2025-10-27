<?php

namespace App\Entity;

use App\Repository\PlanTaskRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PlanTaskRepository::class)]
class PlanTask
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Plan::class, inversedBy: 'taskTemplates')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Plan $plan = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'titleRequired')]
    #[Assert\Length(max: 180)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\NotNull(message: 'difficultyRequired')]
    #[Assert\Range(min: 1, max: 5, notInRangeMessage: 'difficultyRange')]
    private int $difficulty = 3;

    #[ORM\Column]
    private bool $requiresInspection = false;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $position = 0;

    /**
     * @var Collection<int, PlanSubTask>
     */
    #[ORM\OneToMany(targetEntity: PlanSubTask::class, mappedBy: 'taskTemplate', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $subTaskTemplates;

    public function __construct()
    {
        $this->difficulty = 3;
        $this->requiresInspection = false;
        $this->position = 0;
        $this->subTaskTemplates = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->title ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlan(): ?Plan
    {
        return $this->plan;
    }

    public function setPlan(?Plan $plan): static
    {
        $this->plan = $plan;

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

    /**
     * @return Collection<int, PlanSubTask>
     */
    public function getSubTaskTemplates(): Collection
    {
        return $this->subTaskTemplates;
    }

    public function addSubTaskTemplate(PlanSubTask $subTaskTemplate): static
    {
        if (!$this->subTaskTemplates->contains($subTaskTemplate)) {
            $this->subTaskTemplates->add($subTaskTemplate);
            $subTaskTemplate->setTaskTemplate($this);
        }

        return $this;
    }

    public function removeSubTaskTemplate(PlanSubTask $subTaskTemplate): static
    {
        if ($this->subTaskTemplates->removeElement($subTaskTemplate)) {
            if ($subTaskTemplate->getTaskTemplate() === $this) {
                $subTaskTemplate->setTaskTemplate(null);
            }
        }

        return $this;
    }
}

