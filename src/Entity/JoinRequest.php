<?php

namespace App\Entity;

use App\Repository\JoinRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Demande d'un utilisateur vérifié à rejoindre un club existant, émise depuis
 * l'écran « Ce club existe déjà ? ». Les gestionnaires du club la reçoivent par e-mail.
 */
#[ORM\Entity(repositoryClass: JoinRequestRepository::class)]
#[ORM\Table(name: 'join_request')]
class JoinRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Club::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Club $club = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $handledAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'handled_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $handledBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getClub(): ?Club { return $this->club; }
    public function setClub(?Club $club): static { $this->club = $club; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getMessage(): ?string { return $this->message; }
    public function setMessage(?string $message): static { $this->message = $message; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getHandledAt(): ?\DateTimeImmutable { return $this->handledAt; }
    public function getHandledBy(): ?User { return $this->handledBy; }

    public function markHandled(?User $by): static
    {
        $this->handledAt = new \DateTimeImmutable();
        $this->handledBy = $by;

        return $this;
    }

    public function isOpen(): bool
    {
        return $this->handledAt === null;
    }
}
