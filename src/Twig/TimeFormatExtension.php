<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class TimeFormatExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('format_time_spent', [$this, 'formatTimeSpent']),
        ];
    }

    /**
     * Format time spent in hours to a human-readable format (days, hours, minutes)
     * Consider that 1 day = 7 hours
     */
    public function formatTimeSpent(?float $hours): string
    {
        if ($hours === null || $hours <= 0) {
            return '0 minute';
        }

        // Convert to total minutes
        $totalMinutes = (int) round($hours * 60);

        // Calculate days (1 day = 7 hours = 420 minutes)
        $days = (int) floor($totalMinutes / 420);
        $remainingMinutes = $totalMinutes % 420;

        // Calculate hours from remaining minutes
        $hoursRemaining = (int) floor($remainingMinutes / 60);
        $minutesRemaining = $remainingMinutes % 60;

        // Build the result string
        $parts = [];

        if ($days > 0) {
            $parts[] = $days . ' ' . ($days > 1 ? 'jours' : 'jour');
        }

        if ($hoursRemaining > 0) {
            $parts[] = $hoursRemaining . ' ' . ($hoursRemaining > 1 ? 'heures' : 'heure');
        }

        if ($minutesRemaining > 0) {
            $parts[] = $minutesRemaining . ' ' . ($minutesRemaining > 1 ? 'minutes' : 'minute');
        }

        // Handle the case where we only have minutes
        if (empty($parts)) {
            return '0 minute';
        }

        // Join with proper French conjunction
        if (count($parts) === 1) {
            return $parts[0];
        } elseif (count($parts) === 2) {
            return implode(' et ', $parts);
        } else {
            $last = array_pop($parts);
            return implode(', ', $parts) . ' et ' . $last;
        }
    }
}

