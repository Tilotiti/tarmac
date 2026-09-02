<?php

namespace App\Service\HealthCheck\Check;

use App\Entity\Club;
use App\Service\HealthCheck\HealthCheckInterface;
use App\Service\HealthCheck\HealthCheckItem;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.health_check', ['priority' => 100])]
final class AerodromeCheck implements HealthCheckInterface
{
    public function check(Club $club): HealthCheckItem
    {
        return new HealthCheckItem('aerodrome', $club->getAerodrome() !== null, 'club_settings_aerodrome', [], false);
    }
}
