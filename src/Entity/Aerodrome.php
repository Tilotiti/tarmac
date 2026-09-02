<?php

namespace App\Entity;

use App\Repository\AerodromeRepository;
use Doctrine\ORM\Mapping as ORM;
use function Symfony\Component\String\u;

#[ORM\Entity(repositoryClass: AerodromeRepository::class)]
#[ORM\Table(name: 'aerodrome')]
#[ORM\UniqueConstraint(name: 'UNIQ_AERODROME_SOURCE', fields: ['sourceId'])]
#[ORM\Index(name: 'IDX_AERODROME_SEARCH', fields: ['searchIndex'])]
class Aerodrome
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $sourceId = 0;

    #[ORM\Column(length: 4, nullable: true)]
    private ?string $icaoCode = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $municipality = null;

    #[ORM\Column]
    private float $latitude = 0.0;

    #[ORM\Column]
    private float $longitude = 0.0;

    /** Nom + commune + OACI en ASCII minuscule, pour une recherche insensible aux accents. */
    #[ORM\Column(length: 512)]
    private string $searchIndex = '';

    public static function normalizeForSearch(string $text): string
    {
        return u($text)->ascii()->lower()->collapseWhitespace()->toString();
    }

    public function getId(): ?int { return $this->id; }
    public function getSourceId(): int { return $this->sourceId; }
    public function setSourceId(int $sourceId): static { $this->sourceId = $sourceId; return $this; }
    public function getIcaoCode(): ?string { return $this->icaoCode; }
    public function setIcaoCode(?string $icaoCode): static { $this->icaoCode = $icaoCode; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getMunicipality(): ?string { return $this->municipality; }
    public function setMunicipality(?string $municipality): static { $this->municipality = $municipality; return $this; }
    public function getLatitude(): float { return $this->latitude; }
    public function setLatitude(float $latitude): static { $this->latitude = $latitude; return $this; }
    public function getLongitude(): float { return $this->longitude; }
    public function setLongitude(float $longitude): static { $this->longitude = $longitude; return $this; }
    public function getSearchIndex(): string { return $this->searchIndex; }

    public function refreshSearchIndex(): static
    {
        $this->searchIndex = self::normalizeForSearch(implode(' ', array_filter([$this->name, $this->municipality, $this->icaoCode])));

        return $this;
    }

    /**
     * Ex. si le nom contient la commune : « Megève Altiport · LFHM ».
     * Ex. sinon, la commune est ajoutée entre parenthèses : « Altiport (Megève) · LFHM ».
     */
    public function getLabel(): string
    {
        $label = $this->name;
        if ($this->municipality !== null && mb_stripos($this->name, $this->municipality) === false) {
            $label .= ' (' . $this->municipality . ')';
        }
        if ($this->icaoCode !== null) {
            $label .= ' · ' . $this->icaoCode;
        }

        return $label;
    }

    public function __toString(): string
    {
        return $this->getLabel();
    }
}
