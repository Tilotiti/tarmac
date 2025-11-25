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

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $documentation = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dueAt = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['open', 'done', 'closed', 'cancelled'])]
    private string $status = 'open';

    #[ORM\Column]
    private bool $priority = false;

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
    #[Assert\Count(min: 1, minMessage: 'atLeastOneSubTaskRequired')]
    private Collection $subTasks;

    /**
     * @var Collection<int, Activity>
     */
    #[ORM\OneToMany(targetEntity: Activity::class, mappedBy: 'task', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $activities;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = 'open';
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

    public function getDocumentation(): ?string
    {
        return $this->documentation;
    }

    public function setDocumentation(?string $documentation): static
    {
        $this->documentation = $documentation;

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

    /**
     * Get computed difficulty as rounded average of all subtasks
     */
    public function getDifficulty(): int
    {
        $subTasks = $this->subTasks;
        if ($subTasks->count() === 0) {
            return 2; // Default difficulty if no subtasks
        }

        $total = 0;
        foreach ($subTasks as $subTask) {
            $total += $subTask->getDifficulty();
        }

        return (int) round($total / $subTasks->count());
    }

    public function getDifficultyLabel(): string
    {
        $difficulty = $this->getDifficulty();
        return match ($difficulty) {
            1 => 'debutant',
            2 => 'experimente',
            3 => 'expert',
            default => 'experimente',
        };
    }

    public function getDifficultyColor(): string
    {
        $difficulty = $this->getDifficulty();
        return match ($difficulty) {
            1 => 'success',
            2 => 'warning',
            3 => 'danger',
            default => 'warning',
        };
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

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    /**
     * Check if all subtasks are closed (completed or cancelled)
     */
    public function allSubTasksClosed(): bool
    {
        foreach ($this->subTasks as $subTask) {
            if (!$subTask->isClosed() && !$subTask->isCancelled()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if any subtask has been done (marked as done by someone)
     */
    public function hasAnySubTaskDone(): bool
    {
        foreach ($this->subTasks as $subTask) {
            if ($subTask->isDone()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if the task requires inspection (at least one subtask requires inspection)
     */
    public function requiresInspection(): bool
    {
        foreach ($this->subTasks as $subTask) {
            if ($subTask->requiresInspection()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if the task is waiting for subtask inspections
     */
    public function isWaitingForApproval(): bool
    {
        // Task is waiting if any subtask is done and waiting for inspection
        foreach ($this->subTasks as $subTask) {
            if ($subTask->isWaitingForApproval()) {
                return true;
            }
        }
        return false;
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
     * Get total time spent on this task (sum of all subtask contributions)
     */
    public function getTotalTimeSpent(): float
    {
        $total = 0.0;
        foreach ($this->subTasks as $subTask) {
            $total += $subTask->getTotalTimeSpent();
        }
        return $total;
    }

    public function isPriority(): bool
    {
        return $this->priority;
    }

    public function setPriority(bool $priority): static
    {
        $this->priority = $priority;
        
        // If task is set to priority, inherit to all subtasks
        if ($priority) {
            foreach ($this->subTasks as $subTask) {
                $subTask->setPriority(true);
            }
        }
        
        return $this;
    }

    /**
     * Check if any subtask is priority
     */
    public function hasPrioritySubTask(): bool
    {
        foreach ($this->subTasks as $subTask) {
            if ($subTask->isPriority()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if task is priority or has any priority subtask
     */
    public function isPriorityOrHasPrioritySubTask(): bool
    {
        return $this->priority || $this->hasPrioritySubTask();
    }
}

