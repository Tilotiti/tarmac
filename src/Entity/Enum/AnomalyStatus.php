<?php

namespace App\Entity\Enum;

/**
 * Derived status of an anomaly. Never persisted: computed by Anomaly::getStatus()
 * from the resolution and the linked tasks.
 */
enum AnomalyStatus: string
{
    case REPORTED = 'reported';
    case ACKNOWLEDGED = 'acknowledged';
    case TREATED = 'treated';
    case IGNORED = 'ignored';

    public function getLabel(): string
    {
        return match ($this) {
            self::REPORTED => 'anomalyStatusReported',
            self::ACKNOWLEDGED => 'anomalyStatusAcknowledged',
            self::TREATED => 'anomalyStatusTreated',
            self::IGNORED => 'anomalyStatusIgnored',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::REPORTED => 'ti-flag',
            self::ACKNOWLEDGED => 'ti-tool',
            self::TREATED => 'ti-circle-check',
            self::IGNORED => 'ti-eye-off',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::REPORTED => 'warning',
            self::ACKNOWLEDGED => 'info',
            self::TREATED => 'success',
            self::IGNORED => 'secondary',
        };
    }

    /**
     * Statuses shown by default in the anomaly list.
     *
     * @return array<string>
     */
    public static function getDefaultFilter(): array
    {
        return [self::REPORTED->value, self::ACKNOWLEDGED->value];
    }
}
