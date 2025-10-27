<?php

namespace App\Entity\Enum;

enum EquipmentType: string
{
    case GLIDER = 'glider';
    case FACILITY = 'facility';

    public function getLabel(): string
    {
        return match ($this) {
            self::GLIDER => 'glider',
            self::FACILITY => 'infrastructure',
        };
    }

    public static function getChoices(): array
    {
        return [
            'glider' => self::GLIDER->value,
            'infrastructure' => self::FACILITY->value,
        ];
    }
}

