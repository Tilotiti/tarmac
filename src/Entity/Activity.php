<?php

namespace App\Entity;

use App\Entity\Enum\ActivityType;
use App\Repository\ActivityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActivityRepository::class)]
#[ORM\Table(name: 'activity')]
#[ORM\Index(columns: ['created_at'], name: 'idx_activity_created_at')]
#[ORM\Index(columns: ['type'], name: 'idx_activity_type')]
class Activity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Task::class, inversedBy: 'activities')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Task $task = null;

    #[ORM\ManyToOne(targetEntity: SubTask::class, inversedBy: 'activities')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?SubTask $subTask = null;

    #[ORM\Column(length: 30, enumType: ActivityType::class)]
    private ?ActivityType $type = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

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

    public function getTask(): ?Task
    {
        return $this->task;
    }

    public function setTask(?Task $task): static
    {
        $this->task = $task;

        return $this;
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

    public function getType(): ?ActivityType
    {
        return $this->type;
    }

    public function setType(ActivityType $type): static
    {
        $this->type = $type;

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

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

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

    public function isComment(): bool
    {
        return $this->type === ActivityType::COMMENT;
    }

    public function getTypeLabel(): string
    {
        // For context-sensitive labels, we check if it's a subtask activity
        $isSubTask = $this->subTask !== null;

        return match ($this->type) {
            ActivityType::COMMENT => 'comment',
            ActivityType::CREATED => $isSubTask ? 'subTaskCreated' : 'taskCreated',
            ActivityType::EDITED => $isSubTask ? 'subTaskEdited' : 'taskEdited',
            ActivityType::DONE => $isSubTask ? 'subTaskDone' : 'taskDone',
            ActivityType::UNDONE => $isSubTask ? 'subTaskUndone' : 'taskUndone',
            ActivityType::INSPECTED_APPROVED => 'inspectionApproved',
            ActivityType::INSPECTED_REJECTED => 'inspectionRejected',
            ActivityType::CLOSED => 'taskClosed',
            ActivityType::CANCELLED => $isSubTask ? 'subTaskCancelled' : 'taskCancelled',
            ActivityType::APPLICATION_CANCELLED => 'applicationCancelled',
            default => $this->type?->value ?? 'unknown',
        };
    }

    public function getTypeIcon(): string
    {
        return match ($this->type) {
            ActivityType::COMMENT => 'ti-message',
            ActivityType::CREATED => 'ti-plus',
            ActivityType::EDITED => 'ti-edit',
            ActivityType::DONE => 'ti-check',
            ActivityType::UNDONE => 'ti-x',
            ActivityType::INSPECTED_APPROVED => 'ti-circle-check',
            ActivityType::INSPECTED_REJECTED => 'ti-circle-x',
            ActivityType::CLOSED => 'ti-lock',
            ActivityType::CANCELLED, ActivityType::APPLICATION_CANCELLED => 'ti-ban',
            default => 'ti-circle',
        };
    }

    public function getTypeColor(): string
    {
        return match ($this->type) {
            ActivityType::COMMENT => 'blue',
            ActivityType::CREATED => 'info',
            ActivityType::EDITED => 'yellow',
            ActivityType::DONE => 'green',
            ActivityType::UNDONE => 'orange',
            ActivityType::INSPECTED_APPROVED, ActivityType::CLOSED => 'success',
            ActivityType::INSPECTED_REJECTED, ActivityType::CANCELLED, ActivityType::APPLICATION_CANCELLED => 'danger',
            default => 'secondary',
        };
    }
}

