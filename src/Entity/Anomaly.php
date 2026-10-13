<?php

namespace App\Entity;

use App\Entity\Enum\AnomalyImpact;
use App\Entity\Enum\AnomalyResolution;
use App\Entity\Enum\AnomalyStatus;
use App\Repository\AnomalyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AnomalyRepository::class)]
#[ORM\Table(name: 'anomaly')]
#[ORM\Index(columns: ['impact'], name: 'idx_anomaly_impact')]
#[ORM\Index(columns: ['resolution'], name: 'idx_anomaly_resolution')]
#[ORM\Index(columns: ['reported_at'], name: 'idx_anomaly_reported_at')]
class Anomaly
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
    #[Assert\Length(max: 180, maxMessage: 'valueTooLong')]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'descriptionRequired')]
    private ?string $description = null;

    /**
     * Absolute S3 URLs, in upload order. Stored as a plain array rather than a
     * dedicated entity: the upload bundle only ever produces a string, and the
     * "who added this and when" metadata is already carried by the activity log.
     *
     * @var array<int, string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $photos = [];

    #[ORM\Column(type: Types::STRING, length: 20, enumType: AnomalyImpact::class)]
    #[Assert\NotNull(message: 'anomalyImpactRequired')]
    private AnomalyImpact $impact = AnomalyImpact::TO_DETERMINE;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true, enumType: AnomalyResolution::class)]
    private ?AnomalyResolution $resolution = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $resolvedBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    /**
     * The member the anomaly is attributed to. May differ from $createdBy when a
     * manager records an anomaly on behalf of someone else.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Assert\NotNull(message: 'reportedByRequired')]
    private ?User $reportedBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'reportedAtRequired')]
    private ?\DateTimeImmutable $reportedAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * Les sous-tâches qui réparent l'anomalie. Une visite compte des dizaines
     * d'étapes : on rattache celle qui répare, pas la visite entière.
     *
     * @var Collection<int, SubTask>
     */
    #[ORM\ManyToMany(targetEntity: SubTask::class, inversedBy: 'anomalies')]
    #[ORM\JoinTable(name: 'anomaly_sub_task')]
    private Collection $subTasks;

    /**
     * @var Collection<int, Activity>
     */
    #[ORM\OneToMany(targetEntity: Activity::class, mappedBy: 'anomaly', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $activities;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->reportedAt = new \DateTimeImmutable('today');
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

    public function setTitle(?string $title): static
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

    /**
     * @return array<int, string>
     */
    public function getPhotos(): array
    {
        return $this->photos;
    }

    /**
     * @param array<int, string> $photos
     */
    public function setPhotos(array $photos): static
    {
        // Always reassign a fresh, re-indexed array so Doctrine detects the change.
        $this->photos = array_values($photos);

        return $this;
    }

    public function addPhoto(string $url): static
    {
        if (!in_array($url, $this->photos, true)) {
            $this->photos = [...$this->photos, $url];
        }

        return $this;
    }

    public function removePhoto(string $url): static
    {
        $this->photos = array_values(array_filter(
            $this->photos,
            static fn (string $photo): bool => $photo !== $url
        ));

        return $this;
    }

    public function hasPhotos(): bool
    {
        return $this->photos !== [];
    }

    public function getImpact(): AnomalyImpact
    {
        return $this->impact;
    }

    public function setImpact(AnomalyImpact $impact): static
    {
        $this->impact = $impact;

        return $this;
    }

    public function getResolution(): ?AnomalyResolution
    {
        return $this->resolution;
    }

    public function setResolution(?AnomalyResolution $resolution): static
    {
        $this->resolution = $resolution;

        return $this;
    }

    public function getResolvedBy(): ?User
    {
        return $this->resolvedBy;
    }

    public function setResolvedBy(?User $resolvedBy): static
    {
        $this->resolvedBy = $resolvedBy;

        return $this;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?\DateTimeImmutable $resolvedAt): static
    {
        $this->resolvedAt = $resolvedAt;

        return $this;
    }

    public function getReportedBy(): ?User
    {
        return $this->reportedBy;
    }

    public function setReportedBy(?User $reportedBy): static
    {
        $this->reportedBy = $reportedBy;

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

    public function getReportedAt(): ?\DateTimeImmutable
    {
        return $this->reportedAt;
    }

    public function setReportedAt(?\DateTimeImmutable $reportedAt): static
    {
        $this->reportedAt = $reportedAt;

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
            $subTask->addAnomaly($this);
        }

        return $this;
    }

    public function removeSubTask(SubTask $subTask): static
    {
        if ($this->subTasks->removeElement($subTask)) {
            $subTask->removeAnomaly($this);
        }

        return $this;
    }

    /**
     * Linked subtasks that still count: neither the subtask nor its task is
     * cancelled. Cancelling a task only cancels its open subtasks, so a done or
     * closed subtask of a cancelled task must be filtered out here.
     *
     * @return Collection<int, SubTask>
     */
    public function getActiveSubTasks(): Collection
    {
        return $this->subTasks->filter(
            static fn (SubTask $subTask): bool => !$subTask->isCancelled()
                && $subTask->getTask()?->isCancelled() !== true
        );
    }

    /**
     * @return Collection<int, Activity>
     */
    public function getActivities(): Collection
    {
        return $this->activities;
    }

    public function addActivity(Activity $activity): static
    {
        if (!$this->activities->contains($activity)) {
            $this->activities->add($activity);
            $activity->setAnomaly($this);
        }

        return $this;
    }

    /**
     * Displayed status, always derived — never stored, so it can never drift
     * away from the linked subtasks.
     */
    public function getStatus(): AnomalyStatus
    {
        return match (true) {
            $this->resolution === AnomalyResolution::IGNORED => AnomalyStatus::IGNORED,
            $this->resolution === AnomalyResolution::TREATED => AnomalyStatus::TREATED,
            !$this->getActiveSubTasks()->isEmpty() => AnomalyStatus::ACKNOWLEDGED,
            default => AnomalyStatus::REPORTED,
        };
    }

    public function isResolved(): bool
    {
        return $this->resolution !== null;
    }

    public function isTreated(): bool
    {
        return $this->resolution === AnomalyResolution::TREATED;
    }

    public function isIgnored(): bool
    {
        return $this->resolution === AnomalyResolution::IGNORED;
    }

    /**
     * A manager may confirm the anomaly as treated once every active linked
     * subtask is closed (inspection included) — and provided at least one was
     * actually linked.
     */
    public function canBeMarkedAsTreated(): bool
    {
        if ($this->isResolved()) {
            return false;
        }

        $activeSubTasks = $this->getActiveSubTasks();

        if ($activeSubTasks->isEmpty()) {
            return false;
        }

        foreach ($activeSubTasks as $subTask) {
            if (!$subTask->isClosed()) {
                return false;
            }
        }

        return true;
    }

    /**
     * The anomaly currently makes the equipment unusable.
     */
    public function isGrounding(): bool
    {
        return $this->impact->isGrounding() && !$this->isResolved();
    }
}
