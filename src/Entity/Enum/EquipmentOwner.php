<?php

namespace App\Entity\Enum;

enum EquipmentOwner: string
{
    case CLUB = 'club';
    case PRIVATE = 'private';

    public function getLabel(): string
    {
        return match ($this) {
            self::CLUB => 'club',
            self::PRIVATE => 'private',
        };
    }

    public function isPrivate(): bool
    {
        return $this === self::PRIVATE;
    }

    public function isClub(): bool
    {
        return $this === self::CLUB;
    }
}

