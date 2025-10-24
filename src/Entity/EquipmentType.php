<?php

namespace App\Entity;

enum EquipmentType: string
{
    case GLIDER = 'glider';
    case FACILITY = 'facility';

    public function getLabel(): string
    {
        return match ($this) {
            self::GLIDER => 'Planeur',
            self::FACILITY => 'Infrastructure',
        };
    }

    public static function getChoices(): array
    {
        return [
            'Planeur' => self::GLIDER->value,
            'Infrastructure' => self::FACILITY->value,
        ];
    }
}

