<?php

namespace App\Entity;

use App\Entity\Enum\PurchaseStatus;
use App\Repository\PurchaseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PurchaseRepository::class)]
#[ORM\Table(name: 'purchase')]
#[ORM\Index(columns: ['status'], name: 'idx_purchase_status')]
#[ORM\Index(columns: ['created_at'], name: 'idx_purchase_created_at')]
class Purchase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Club::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Club $club = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'nameRequired')]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(1)]
    private int $quantity = 1;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', enumType: PurchaseStatus::class)]
    private PurchaseStatus $status = PurchaseStatus::REQUESTED;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $requestImage = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $billImage = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $purchasedBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $purchasedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $deliveredBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expectedDeliveryDate = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $reimbursedBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $reimbursedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $cancelledBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $approvedBy = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    /**
     * @var Collection<int, Activity>
     */
    #[ORM\OneToMany(targetEntity: Activity::class, mappedBy: 'purchase', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $activities;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = PurchaseStatus::REQUESTED;
        $this->quantity = 1;
        $this->activities = new ArrayCollection();
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

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

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

    public function getStatus(): PurchaseStatus
    {
        return $this->status;
    }

    public function setStatus(PurchaseStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getRequestImage(): ?string
    {
        return $this->requestImage;
    }

    public function setRequestImage(?string $requestImage): static
    {
        $this->requestImage = $requestImage;

        return $this;
    }

    public function getBillImage(): ?string
    {
        return $this->billImage;
    }

    public function setBillImage(?string $billImage): static
    {
        $this->billImage = $billImage;

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

    public function getPurchasedBy(): ?User
    {
        return $this->purchasedBy;
    }

    public function setPurchasedBy(?User $purchasedBy): static
    {
        $this->purchasedBy = $purchasedBy;

        return $this;
    }

    public function getPurchasedAt(): ?\DateTimeImmutable
    {
        return $this->purchasedAt;
    }

    public function setPurchasedAt(?\DateTimeImmutable $purchasedAt): static
    {
        $this->purchasedAt = $purchasedAt;

        return $this;
    }

    public function getDeliveredBy(): ?User
    {
        return $this->deliveredBy;
    }

    public function setDeliveredBy(?User $deliveredBy): static
    {
        $this->deliveredBy = $deliveredBy;

        return $this;
    }

    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(?\DateTimeImmutable $deliveredAt): static
    {
        $this->deliveredAt = $deliveredAt;

        return $this;
    }

    public function getExpectedDeliveryDate(): ?\DateTimeImmutable
    {
        return $this->expectedDeliveryDate;
    }

    public function setExpectedDeliveryDate(?\DateTimeImmutable $expectedDeliveryDate): static
    {
        $this->expectedDeliveryDate = $expectedDeliveryDate;

        return $this;
    }

    public function getReimbursedBy(): ?User
    {
        return $this->reimbursedBy;
    }

    public function setReimbursedBy(?User $reimbursedBy): static
    {
        $this->reimbursedBy = $reimbursedBy;

        return $this;
    }

    public function getReimbursedAt(): ?\DateTimeImmutable
    {
        return $this->reimbursedAt;
    }

    public function setReimbursedAt(?\DateTimeImmutable $reimbursedAt): static
    {
        $this->reimbursedAt = $reimbursedAt;

        return $this;
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

    public function getApprovedBy(): ?User
    {
        return $this->approvedBy;
    }

    public function setApprovedBy(?User $approvedBy): static
    {
        $this->approvedBy = $approvedBy;

        return $this;
    }

    public function getApprovedAt(): ?\DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(?\DateTimeImmutable $approvedAt): static
    {
        $this->approvedAt = $approvedAt;

        return $this;
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
            $activity->setPurchase($this);
        }

        return $this;
    }

    public function removeActivity(Activity $activity): static
    {
        if ($this->activities->removeElement($activity)) {
            if ($activity->getPurchase() === $this) {
                $activity->setPurchase(null);
            }
        }

        return $this;
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, [
            PurchaseStatus::COMPLETE,
            PurchaseStatus::REIMBURSED,
            PurchaseStatus::CANCELLED,
        ]);
    }

    public function isPendingApproval(): bool
    {
        return $this->status === PurchaseStatus::PENDING_APPROVAL;
    }

    public function canEdit(User $user, bool $isManager): bool
    {
        // Cannot edit if cancelled or reimbursed
        if (in_array($this->status, [PurchaseStatus::CANCELLED, PurchaseStatus::REIMBURSED])) {
            return false;
        }

        // Managers can always edit (unless cancelled/reimbursed)
        if ($isManager) {
            return true;
        }

        // Members can edit if they created, purchased, or marked as delivered
        $userId = $user->getId();
        
        if ($this->createdBy && $this->createdBy->getId() === $userId) {
            return true;
        }
        
        if ($this->purchasedBy && $this->purchasedBy->getId() === $userId) {
            return true;
        }
        
        if ($this->deliveredBy && $this->deliveredBy->getId() === $userId) {
            return true;
        }

        return false;
    }

    public function canCancel(User $user, bool $isManager): bool
    {
        if ($this->isCompleted()) {
            return false;
        }

        if ($isManager) {
            return true;
        }

        return $this->createdBy && $this->createdBy->getId() === $user->getId();
    }

    public function canMarkPurchased(): bool
    {
        return in_array($this->status, [
            PurchaseStatus::REQUESTED,
            PurchaseStatus::APPROVED,
        ]);
    }

    public function canMarkDelivered(): bool
    {
        return $this->status === PurchaseStatus::PURCHASED;
    }

    public function canMarkReimbursed(): bool
    {
        return $this->status === PurchaseStatus::COMPLETE;
    }

    /**
     * Get the next primary action for a user
     * Returns: 'approve', 'mark_purchased', 'mark_delivered', 'mark_reimbursed', or null
     */
    public function getNextPrimaryAction(User $user, bool $isManager): ?string
    {
        // Priority 1: If complete, manager can mark as reimbursed
        if ($this->status === PurchaseStatus::COMPLETE && $isManager) {
            return 'mark_reimbursed';
        }

        // If reimbursed or cancelled, no primary action
        if (in_array($this->status, [PurchaseStatus::REIMBURSED, PurchaseStatus::CANCELLED])) {
            return null;
        }

        // Priority 2: If pending approval and user is manager, approve
        if ($this->status === PurchaseStatus::PENDING_APPROVAL && $isManager) {
            return 'approve';
        }

        // Priority 3: If approved or requested, can mark as purchased
        if ($this->canMarkPurchased()) {
            return 'mark_purchased';
        }

        // Priority 4: If purchased, can mark as delivered
        if ($this->canMarkDelivered()) {
            return 'mark_delivered';
        }

        return null;
    }

    /**
     * Get only purchase-level activities (ordered by createdAt ASC for timeline display)
     * 
     * @return Collection<int, Activity>
     */
    public function getMainActivities(): Collection
    {
        $criteria = Criteria::create()
            ->orderBy(['createdAt' => Criteria::ASC]);

        /** @var \Doctrine\Common\Collections\Selectable $activities */
        $activities = $this->activities;
        return $activities->matching($criteria);
    }
}

