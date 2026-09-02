<?php

namespace App\Service\HealthCheck\Check;

use App\Entity\Club;
use App\Service\HealthCheck\HealthCheckInterface;
use App\Service\HealthCheck\HealthCheckItem;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.health_check', ['priority' => 40])]
final class WelcomeMessageCheck implements HealthCheckInterface
{
    public function check(Club $club): HealthCheckItem
    {
        return new HealthCheckItem('welcomeMessage', trim((string) $club->getWelcomeMessage()) !== '', 'club_dashboard_edit_welcome_message', [], false);
    }
}
