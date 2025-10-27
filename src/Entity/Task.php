<?php

namespace App\Entity;

use App\Entity\Enum\EquipmentType;
use App\Repository\TaskRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Index(columns: ['status'], name: 'idx_task_status')]
#[ORM\Index(columns: ['due_at'], name: 'idx_task_due_at')]
#[ORM\Index(columns: ['difficulty'], name: 'idx_task_difficulty')]
#[ORM\Index(columns: ['created_at'], name: 'idx_task_created_at')]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Club::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Club $club = null;

    #[ORM\ManyToOne(targetEntity: Equipment::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'equipmentRequired')]
    private ?Equipment $equipment = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'titleRequired')]
    #[Assert\Length(max: 180)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dueAt = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\NotNull(message: 'difficultyRequired')]
    #[Assert\Range(min: 1, max: 5, notInRangeMessage: 'difficultyRange')]
    private int $difficulty = 3;

    #[ORM\Column]
    private bool $requiresInspection = false;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['open', 'closed', 'cancelled'])]
    private string $status = 'open';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $doneBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $doneAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $inspectedBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $inspectedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $cancelledBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: PlanApplication::class, inversedBy: 'tasks')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?PlanApplication $planApplication = null;

    /**
     * @var Collection<int, SubTask>
     */
    #[ORM\OneToMany(targetEntity: SubTask::class, mappedBy: 'task', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $subTasks;

    /**
     * @var Collection<int, Activity>
     */
    #[ORM\OneToMany(targetEntity: Activity::class, mappedBy: 'task', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $activities;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = 'open';
        $this->difficulty = 3;
        $this->requiresInspection = false;
        $this->subTasks = new ArrayCollection();
        $this->activities = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->title ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClub(): ?Club
    {
        return $this->club;
    }

    public function setClub(?Club $club): static
    {
        $this->club = $club;

        return $this;
    }

    public function getEquipment(): ?Equipment
    {
        return $this->equipment;
    }

    public function setEquipment(?Equipment $equipment): static
    {
        $this->equipment = $equipment;

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

    public function getDueAt(): ?\DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function setDueAt(?\DateTimeImmutable $dueAt): static
    {
        $this->dueAt = $dueAt;

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

    public function getDifficultyLabel(): string
    {
        return match ($this->difficulty) {
            1 => 'debutant',
            2 => 'facile',
            3 => 'moyen',
            4 => 'difficile',
            5 => 'expert',
            default => 'moyen',
        };
    }

    public function getDifficultyColor(): string
    {
        return match ($this->difficulty) {
            1 => 'success',
            2 => 'success-lt',
            3 => 'warning',
            4 => 'orange',
            5 => 'danger',
            default => 'warning',
        };
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function getDoneBy(): ?User
    {
        return $this->doneBy;
    }

    public function setDoneBy(?User $doneBy): static
    {
        $this->doneBy = $doneBy;

        return $this;
    }

    public function getDoneAt(): ?\DateTimeImmutable
    {
        return $this->doneAt;
    }

    public function setDoneAt(?\DateTimeImmutable $doneAt): static
    {
        $this->doneAt = $doneAt;

        return $this;
    }

    public function isDone(): bool
    {
        return $this->doneBy !== null;
    }

    /**
     * Check if the task is done and waiting for inspection approval
     */
    public function isWaitingForApproval(): bool
    {
        return $this->isDone()
            && $this->requiresInspection
            && !$this->isInspected()
            && $this->status === 'open';
    }

    public function getInspectedBy(): ?User
    {
        return $this->inspectedBy;
    }

    public function setInspectedBy(?User $inspectedBy): static
    {
        $this->inspectedBy = $inspectedBy;

        return $this;
    }

    public function getInspectedAt(): ?\DateTimeImmutable
    {
        return $this->inspectedAt;
    }

    public function setInspectedAt(?\DateTimeImmutable $inspectedAt): static
    {
        $this->inspectedAt = $inspectedAt;

        return $this;
    }

    public function isInspected(): bool
    {
        return $this->inspectedBy !== null;
    }

    public function getCancelledBy(): ?User
    {
        return $this->cancelledBy;
    }

    public function setCancelledBy(?User $cancelledBy): static
    {
        $this->cancelledBy = $cancelledBy;

        return $this;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTimeImmutable $cancelledAt): static
    {
        $this->cancelledAt = $cancelledAt;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

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

    public function getPlanApplication(): ?PlanApplication
    {
        return $this->planApplication;
    }

    public function setPlanApplication(?PlanApplication $planApplication): static
    {
        $this->planApplication = $planApplication;

        return $this;
    }

    /**
     * @return Collection<int, SubTask>
     */
    public function getSubTasks(): Collection
    {
        return $this->subTasks;
    }

    public function addSubTask(SubTask $subTask): static
    {
        if (!$this->subTasks->contains($subTask)) {
            $this->subTasks->add($subTask);
            $subTask->setTask($this);
        }

        return $this;
    }

    public function removeSubTask(SubTask $subTask): static
    {
        if ($this->subTasks->removeElement($subTask)) {
            if ($subTask->getTask() === $this) {
                $subTask->setTask(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Activity>
     */
    public function getActivities(): Collection
    {
        return $this->activities;
    }

    /**
     * Get only task-level activities (excluding subtask activities)
     * Ordered by createdAt ASC for timeline display
     * 
     * @return Collection<int, Activity>
     */
    public function getMainActivities(): Collection
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->isNull('subTask'))
            ->orderBy(['createdAt' => Criteria::ASC]);

        // ArrayCollection implements Selectable which provides the matching() method
        /** @var \Doctrine\Common\Collections\Selectable $activities */
        $activities = $this->activities;
        return $activities->matching($criteria);
    }

    public function addActivity(Activity $activity): static
    {
        if (!$this->activities->contains($activity)) {
            $this->activities->add($activity);
            $activity->setTask($this);
        }

        return $this;
    }

    public function removeActivity(Activity $activity): static
    {
        if ($this->activities->removeElement($activity)) {
            if ($activity->getTask() === $this) {
                $activity->setTask(null);
            }
        }

        return $this;
    }

    /**
     * Check if the task requires inspection AND the equipment is a glider
     */
    public function canRequireInspection(): bool
    {
        return $this->equipment && $this->equipment->getType() === EquipmentType::GLIDER;
    }
}

