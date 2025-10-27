<?php

namespace App\Entity;

use App\Repository\PlanRepository;
use App\Entity\Enum\EquipmentType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PlanRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Plan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Club::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Club $club = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'nameRequired')]
    #[Assert\Length(max: 180)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', enumType: EquipmentType::class)]
    #[Assert\NotNull(message: 'equipmentTypeRequired')]
    private ?EquipmentType $equipmentType = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, PlanTask>
     */
    #[ORM\OneToMany(targetEntity: PlanTask::class, mappedBy: 'plan', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $taskTemplates;

    /**
     * @var Collection<int, PlanApplication>
     */
    #[ORM\OneToMany(targetEntity: PlanApplication::class, mappedBy: 'plan')]
    private Collection $applications;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->taskTemplates = new ArrayCollection();
        $this->applications = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->name ?? '';
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function getEquipmentType(): ?EquipmentType
    {
        return $this->equipmentType;
    }

    public function setEquipmentType(EquipmentType $equipmentType): static
    {
        $this->equipmentType = $equipmentType;

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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, PlanTask>
     */
    public function getTaskTemplates(): Collection
    {
        return $this->taskTemplates;
    }

    public function addTaskTemplate(PlanTask $taskTemplate): static
    {
        if (!$this->taskTemplates->contains($taskTemplate)) {
            $this->taskTemplates->add($taskTemplate);
            $taskTemplate->setPlan($this);
        }

        return $this;
    }

    public function removeTaskTemplate(PlanTask $taskTemplate): static
    {
        if ($this->taskTemplates->removeElement($taskTemplate)) {
            if ($taskTemplate->getPlan() === $this) {
                $taskTemplate->setPlan(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, PlanApplication>
     */
    public function getApplications(): Collection
    {
        return $this->applications;
    }

    public function addApplication(PlanApplication $application): static
    {
        if (!$this->applications->contains($application)) {
            $this->applications->add($application);
            $application->setPlan($this);
        }

        return $this;
    }

    public function removeApplication(PlanApplication $application): static
    {
        if ($this->applications->removeElement($application)) {
            if ($application->getPlan() === $this) {
                $application->setPlan(null);
            }
        }

        return $this;
    }
}

