<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe déjà avec cet e-mail')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column(nullable: true)]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $firstname = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastname = null;

    #[ORM\Column]
    private ?bool $active = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private bool $isVerified = false;

    #[ORM\OneToMany(targetEntity: Membership::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private \Doctrine\Common\Collections\Collection $memberships;

    public function __construct()
    {
        $this->active = true;
        $this->roles = ['ROLE_USER'];
        $this->createdAt = new \DateTimeImmutable();
        $this->memberships = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function __sleep(): array
    {
        return ['id', 'email', 'roles', 'password', 'firstname', 'lastname', 'active', 'createdAt', 'isVerified'];
    }

    public function __wakeup(): void
    {
        // Ensure roles is always an array
        if (!is_array($this->roles)) {
            $this->roles = ['ROLE_USER'];
        }
    }

    public function __toString(): string
    {
        return $this->getFullName() ?? $this->email ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(?string $firstname): static
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(?string $lastname): static
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getFullName(): ?string
    {
        if ($this->firstname && $this->lastname) {
            return trim($this->firstname . ' ' . $this->lastname);
        }

        return $this->firstname ?? $this->lastname;
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

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    /**
     * Get status label for display
     */
    public function getStatusLabel(): string
    {
        return $this->active ? 'Actif' : 'Inactif';
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return in_array('ROLE_ADMIN', $this->getRoles(), true);
    }

    /**
     * Get user's club memberships
     */
    public function getMemberships(): \Doctrine\Common\Collections\Collection
    {
        return $this->memberships;
    }

    /**
     * Add a club membership
     */
    public function addMembership(Membership $membership): static
    {
        if (!$this->memberships->contains($membership)) {
            $this->memberships->add($membership);
            $membership->setUser($this);
        }

        return $this;
    }

    /**
     * Remove a club membership
     */
    public function removeMembership(Membership $membership): static
    {
        if ($this->memberships->removeElement($membership)) {
            // set the owning side to null (unless already changed)
            if ($membership->getUser() === $this) {
                $membership->setUser(null);
            }
        }

        return $this;
    }

    /**
     * Check if user has access to a specific club
     */
    public function hasAccessToClub(Club $club): bool
    {
        foreach ($this->memberships as $membership) {
            if ($membership->getClub() === $club) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user is a manager of a specific club
     */
    public function isManagerOfClub(Club $club): bool
    {
        foreach ($this->memberships as $membership) {
            if ($membership->getClub() === $club) {
                return $membership->isManager();
            }
        }

        return false;
    }

    /**
     * Check if user is an inspector of a specific club
     */
    public function isInspectorOfClub(Club $club): bool
    {
        foreach ($this->memberships as $membership) {
            if ($membership->getClub() === $club) {
                return $membership->isInspector();
            }
        }

        return false;
    }

    /**
     * Check if user has manager access to at least one club
     */
    public function hasManagerAccess(): bool
    {
        foreach ($this->memberships as $membership) {
            if ($membership->isManager()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all clubs the user has access to
     *
     * @return Club[]
     */
    public function getClubs(): array
    {
        return $this->memberships->map(fn(Membership $m) => $m->getClub())->toArray();
    }
}





