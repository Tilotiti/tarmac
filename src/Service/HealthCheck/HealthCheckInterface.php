<?php

namespace App\Service\HealthCheck;

use App\Entity\Club;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Un contrôle de configuration du club. Implémentations taguées app.health_check,
 * ordonnées par la priorité du tag (la plus haute en premier).
 */
#[AutoconfigureTag('app.health_check')]
interface HealthCheckInterface
{
    public function check(Club $club): HealthCheckItem;
}
