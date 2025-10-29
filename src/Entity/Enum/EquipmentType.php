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

    public function getIcon(): string
    {
        return match ($this) {
            self::GLIDER => 'ti-plane-tilt',
            self::AIRPLANE => 'ti-send',
            self::FACILITY => 'ti-building',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::GLIDER => 'blue',
            self::AIRPLANE => 'green',
            self::FACILITY => 'purple',
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

