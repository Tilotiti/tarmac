<?php

namespace App\Tests\Service\Import;

use App\Entity\Club;
use App\Entity\Enum\EquipmentOwner;
use App\Entity\Enum\EquipmentType;
use App\Entity\Equipment;
use App\Entity\User;
use App\Repository\EquipmentRepository;
use App\Service\Import\Definition\EquipmentImportDefinition;
use App\Service\Import\ImportContext;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class EquipmentImportDefinitionTest extends TestCase
{
    private function definition(array $existingNames, array &$persisted): EquipmentImportDefinition
    {
        $repository = $this->createStub(EquipmentRepository::class);
        $repository->method('findActiveNamesByClub')->willReturn($existingNames);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void { $persisted[] = $entity; });

        return new EquipmentImportDefinition($repository, $entityManager);
    }

    public function testFieldsAndSynonyms(): void
    {
        $persisted = [];
        $keys = array_map(static fn ($field) => $field->key, $this->definition([], $persisted)->fields());

        $this->assertSame(['name', 'type', 'description', 'owner'], $keys);
    }

    public function testValidateRejectsDuplicatesInClubAndInFile(): void
    {
        $persisted = [];
        $definition = $this->definition(['F-CGAB'], $persisted);
        $context = new ImportContext(new Club(), new User());

        $this->assertSame('importReasonAlreadyExists', $definition->validate(['name' => 'f-cgab ', 'type' => 'glider', 'description' => '', 'owner' => 'club'], $context));
        $this->assertNull($definition->validate(['name' => 'F-CHXY', 'type' => 'glider', 'description' => '', 'owner' => 'club'], $context));
        $this->assertSame('importReasonDuplicateInFile', $definition->validate(['name' => 'F-CHXY', 'type' => 'airplane', 'description' => '', 'owner' => 'club'], $context));
    }

    public function testValidateRejectsNameTooLong(): void
    {
        $persisted = [];
        $definition = $this->definition([], $persisted);
        $context = new ImportContext(new Club(), new User());

        $this->assertSame('importReasonTooLong', $definition->validate(['name' => str_repeat('a', 256), 'type' => 'glider', 'description' => '', 'owner' => 'club'], $context));
        $this->assertNull($definition->validate(['name' => str_repeat('a', 255), 'type' => 'glider', 'description' => '', 'owner' => 'club'], $context));
    }

    public function testImportCreatesEquipment(): void
    {
        $persisted = [];
        $definition = $this->definition([], $persisted);
        $club = new Club();
        $user = new User();

        $definition->import(['name' => 'Treuil n°2', 'type' => 'facility', 'description' => 'Treuil principal', 'owner' => 'private'], new ImportContext($club, $user));

        $this->assertCount(1, $persisted);
        $equipment = $persisted[0];
        $this->assertInstanceOf(Equipment::class, $equipment);
        $this->assertSame('Treuil n°2', $equipment->getName());
        $this->assertSame(EquipmentType::FACILITY, $equipment->getType());
        $this->assertSame(EquipmentOwner::PRIVATE, $equipment->getOwner());
        $this->assertSame('Treuil principal', $equipment->getDescription());
        $this->assertSame($club, $equipment->getClub());
        $this->assertSame($user, $equipment->getCreatedBy());
        $this->assertTrue($equipment->isActive());
    }

    public function testFinalizeDoesNothing(): void
    {
        $persisted = [];
        $definition = $this->definition([], $persisted);

        $definition->finalize(new ImportContext(new Club(), new User()));

        $this->assertSame([], $persisted);
    }
}
