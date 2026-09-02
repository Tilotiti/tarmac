<?php

namespace App\Service\HealthCheck\Check;

use App\Entity\Club;
use App\Repository\EquipmentRepository;
use App\Service\HealthCheck\HealthCheckInterface;
use App\Service\HealthCheck\HealthCheckItem;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.health_check', ['priority' => 90])]
final class EquipmentCheck implements HealthCheckInterface
{
    public function __construct(private readonly EquipmentRepository $equipmentRepository) {}

    public function check(Club $club): HealthCheckItem
    {
        return new HealthCheckItem('equipment', $this->equipmentRepository->countActiveByClub($club) > 0, 'club_equipments', [], true);
    }
}
