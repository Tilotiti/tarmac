<?php

namespace App\Entity;

use App\Repository\SpecialisationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SpecialisationRepository::class)]
#[ORM\Table(name: 'specialisation')]
#[UniqueEntity(fields: ['club', 'name'], message: 'specialisationNameUniqueForClub')]
class Specialisation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Club::class, inversedBy: 'specialisations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Club $club = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank(message: 'nameRequired')]
    #[Assert\Length(max: 80)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * @var Collection<int, SubTask>
     */
    #[ORM\ManyToMany(targetEntity: SubTask::class, mappedBy: 'specialisations')]
    private Collection $subTasks;

    /**
     * @var Collection<int, PlanSubTask>
     */
    #[ORM\ManyToMany(targetEntity: PlanSubTask::class, mappedBy: 'specialisations')]
    private Collection $planSubTasks;

    public function __construct()
    {
        $this->subTasks = new ArrayCollection();
        $this->planSubTasks = new ArrayCollection();
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
            $subTask->addSpecialisation($this);
        }

        return $this;
    }

    public function removeSubTask(SubTask $subTask): static
    {
        if ($this->subTasks->removeElement($subTask)) {
            $subTask->removeSpecialisation($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, PlanSubTask>
     */
    public function getPlanSubTasks(): Collection
    {
        return $this->planSubTasks;
    }

    public function addPlanSubTask(PlanSubTask $planSubTask): static
    {
        if (!$this->planSubTasks->contains($planSubTask)) {
            $this->planSubTasks->add($planSubTask);
            $planSubTask->addSpecialisation($this);
        }

        return $this;
    }

    public function removePlanSubTask(PlanSubTask $planSubTask): static
    {
        if ($this->planSubTasks->removeElement($planSubTask)) {
            $planSubTask->removeSpecialisation($this);
        }

        return $this;
    }
}
