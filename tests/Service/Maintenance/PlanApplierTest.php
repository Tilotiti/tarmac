<?php

namespace App\Tests\Service\Maintenance;

use App\Entity\Club;
use App\Entity\Equipment;
use App\Entity\Enum\EquipmentType;
use App\Entity\Plan;
use App\Entity\PlanSubTask;
use App\Entity\PlanTask;
use App\Entity\Specialisation;
use App\Entity\SubTask;
use App\Entity\User;
use App\Service\Maintenance\PlanApplier;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class PlanApplierTest extends TestCase
{
    public function testApplyPlanCopiesSpecialisationsFromPlanSubTasks(): void
    {
        $persistedSubTasks = [];

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persistedSubTasks): void {
                if ($entity instanceof SubTask) {
                    $persistedSubTasks[] = $entity;
                }
            });
        $entityManager->expects($this->once())->method('flush');

        $club = (new Club())
            ->setName('Club test')
            ->setSubdomain('club-test');

        $specialisationA = (new Specialisation())
            ->setClub($club)
            ->setName('Moteur');
        $specialisationB = (new Specialisation())
            ->setClub($club)
            ->setName('Cellule');

        $planSubTask = (new PlanSubTask())
            ->setTitle('Sous-tache plan')
            ->setPosition(0);
        $planSubTask->addSpecialisation($specialisationA);
        $planSubTask->addSpecialisation($specialisationB);

        $planTask = (new PlanTask())
            ->setTitle('Tache plan')
            ->setPosition(0);
        $planTask->addSubTaskTemplate($planSubTask);

        $plan = (new Plan())
            ->setClub($club)
            ->setName('Plan test')
            ->setEquipmentType(EquipmentType::GLIDER);
        $plan->addTaskTemplate($planTask);

        $equipment = (new Equipment())
            ->setName('F-CODE')
            ->setClub($club)
            ->setType(EquipmentType::GLIDER);

        $user = (new User())->setEmail('test@example.com');

        $service = new PlanApplier($entityManager);
        $service->applyPlan($plan, $equipment, $user);

        $this->assertCount(1, $persistedSubTasks);
        $createdSubTask = $persistedSubTasks[0];
        $this->assertCount(2, $createdSubTask->getSpecialisations());
        $this->assertTrue($createdSubTask->getSpecialisations()->contains($specialisationA));
        $this->assertTrue($createdSubTask->getSpecialisations()->contains($specialisationB));
    }
}

