<?php

namespace App\Entity\Enum;

enum AnomalyImpact: string
{
    case TO_DETERMINE = 'to_determine';
    case NORMAL = 'normal';
    case GROUNDED = 'grounded';

    public function getLabel(): string
    {
        return match ($this) {
            self::TO_DETERMINE => 'anomalyImpactToDetermine',
            self::NORMAL => 'anomalyImpactNormal',
            self::GROUNDED => 'anomalyImpactGrounded',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::TO_DETERMINE => 'ti-help-circle',
            self::NORMAL => 'ti-circle-check',
            self::GROUNDED => 'ti-alert-triangle',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::TO_DETERMINE => 'secondary',
            self::NORMAL => 'success',
            self::GROUNDED => 'danger',
        };
    }

    /**
     * Whether this impact makes the equipment unusable.
     */
    public function isGrounding(): bool
    {
        return $this === self::GROUNDED;
    }
}
