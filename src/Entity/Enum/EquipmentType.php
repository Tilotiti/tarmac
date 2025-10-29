<?php

namespace App\Entity\Enum;

enum EquipmentType: string
{
    case GLIDER = 'glider';
    case AIRPLANE = 'airplane';
    case FACILITY = 'facility';

    public function getLabel(): string
    {
        return match ($this) {
            self::GLIDER => 'glider',
            self::AIRPLANE => 'airplane',
            self::FACILITY => 'infrastructure',
        };
    }

    public function isAircraft(): bool
    {
        return match ($this) {
            self::GLIDER, self::AIRPLANE => true,
            self::FACILITY => false,
        };
    }

    public static function getChoices(): array
    {
        return [
            'glider' => self::GLIDER->value,
            'airplane' => self::AIRPLANE->value,
            'infrastructure' => self::FACILITY->value,
        ];
    }
}

