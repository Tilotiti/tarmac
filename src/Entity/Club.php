<?php

namespace App\Entity;

use App\Repository\ClubRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: ClubRepository::class)]
#[ORM\Table(name: 'club')]
#[ORM\UniqueConstraint(name: 'UNIQ_CLUB_SUBDOMAIN', fields: ['subdomain'])]
#[UniqueEntity(fields: ['subdomain'], message: 'subdomainUnique')]
class Club
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $subdomain = null;

    #[ORM\Column]
    private ?bool $active = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $welcomeMessage = null;

    /**
     * @var Collection<int, Membership>
     */
    #[ORM\OneToMany(targetEntity: Membership::class, mappedBy: 'club', cascade: ['persist', 'remove'], orphanRemoval: true, fetch: 'EXTRA_LAZY')]
    private Collection $memberships;

    /**
     * @var Collection<int, Invitation>
     */
    #[ORM\OneToMany(targetEntity: Invitation::class, mappedBy: 'club', cascade: ['persist', 'remove'], orphanRemoval: true, fetch: 'EXTRA_LAZY')]
    private Collection $invitations;

    /**
     * @var Collection<int, Equipment>
     */
    #[ORM\OneToMany(targetEntity: Equipment::class, mappedBy: 'club', cascade: ['persist', 'remove'], orphanRemoval: true, fetch: 'EXTRA_LAZY')]
    private Collection $equipments;

    public function __construct()
    {
        $this->active = true;
        $this->createdAt = new \DateTimeImmutable();
        $this->memberships = new ArrayCollection();
        $this->invitations = new ArrayCollection();
        $this->equipments = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSubdomain(): ?string
    {
        return $this->subdomain;
    }

    public function setSubdomain(string $subdomain): static
    {
        $this->subdomain = $subdomain;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

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

    public function getWelcomeMessage(): ?string
    {
        return $this->welcomeMessage;
    }

    public function setWelcomeMessage(?string $welcomeMessage): static
    {
        $this->welcomeMessage = $welcomeMessage;

        return $this;
    }

    /**
     * @return Collection<int, Membership>
     */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    public function addMembership(Membership $membership): static
    {
        if (!$this->memberships->contains($membership)) {
            $this->memberships->add($membership);
            $membership->setClub($this);
        }

        return $this;
    }

    public function removeMembership(Membership $membership): static
    {
        if ($this->memberships->removeElement($membership)) {
            // set the owning side to null (unless already changed)
            if ($membership->getClub() === $this) {
                $membership->setClub(null);
            }
        }

        return $this;
    }

    /**
     * Get all users who have access to this club
     *
     * @return User[]
     */
    public function getUsers(): array
    {
        return $this->memberships->map(fn(Membership $m) => $m->getUser())->toArray();
    }

    /**
     * Check if a user has access to this club
     */
    public function hasUser(User $user): bool
    {
        return $this->memberships->exists(fn(int $key, Membership $m) => $m->getUser() === $user);
    }

    /**
     * Get Membership relationship for a specific user
     */
    public function getMembership(User $user): ?Membership
    {
        foreach ($this->memberships as $membership) {
            if ($membership->getUser() === $user) {
                return $membership;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, Invitation>
     */
    public function getInvitations(): Collection
    {
        return $this->invitations;
    }

    public function addInvitation(Invitation $invitation): static
    {
        if (!$this->invitations->contains($invitation)) {
            $this->invitations->add($invitation);
            $invitation->setClub($this);
        }

        return $this;
    }

    public function removeInvitation(Invitation $invitation): static
    {
        if ($this->invitations->removeElement($invitation)) {
            // set the owning side to null (unless already changed)
            if ($invitation->getClub() === $this) {
                $invitation->setClub(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Equipment>
     */
    public function getEquipments(): Collection
    {
        return $this->equipments;
    }

    public function addEquipment(Equipment $equipment): static
    {
        if (!$this->equipments->contains($equipment)) {
            $this->equipments->add($equipment);
            $equipment->setClub($this);
        }

        return $this;
    }

    public function removeEquipment(Equipment $equipment): static
    {
        if ($this->equipments->removeElement($equipment)) {
            // set the owning side to null (unless already changed)
            if ($equipment->getClub() === $this) {
                $equipment->setClub(null);
            }
        }

        return $this;
    }
}

