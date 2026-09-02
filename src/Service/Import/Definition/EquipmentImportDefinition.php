<?php

namespace App\Service\Import\Definition;

use App\Entity\Enum\EquipmentOwner;
use App\Entity\Enum\EquipmentType;
use App\Entity\Equipment;
use App\Repository\EquipmentRepository;
use App\Service\Import\ColumnMapper;
use App\Service\Import\ImportChoice;
use App\Service\Import\ImportContext;
use App\Service\Import\ImportDefinitionInterface;
use App\Service\Import\ImportField;
use Doctrine\ORM\EntityManagerInterface;

final class EquipmentImportDefinition implements ImportDefinitionInterface
{
    public function __construct(
        private readonly EquipmentRepository $equipmentRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function key(): string
    {
        return 'equipments';
    }

    public function titleKey(): string
    {
        return 'importEquipmentsTitle';
    }

    public function listRoute(): string
    {
        return 'club_equipments';
    }

    public function previewTemplate(): string
    {
        return 'club/import/preview.html.twig';
    }

    public function fields(): array
    {
        return [
            new ImportField('name', 'name', true, ['nom', 'appareil', 'designation', 'immatriculation', 'immat', 'materiel', 'libelle']),
            new ImportField('type', 'type', true, ['type', 'categorie', 'famille', 'genre'], [
                new ImportChoice(EquipmentType::GLIDER->value, 'glider', ['planeur', 'glider', 'motoplaneur']),
                new ImportChoice(EquipmentType::AIRPLANE->value, 'airplane', ['avion', 'ulm', 'remorqueur', 'airplane']),
                new ImportChoice(EquipmentType::FACILITY->value, 'infrastructure', ['treuil', 'remorque', 'hangar', 'vehicule', 'infra', 'batiment']),
            ]),
            new ImportField('description', 'description', false, ['description', 'remarque', 'commentaire', 'notes', 'modele']),
            new ImportField('owner', 'owner', false, ['proprietaire', 'appartenance'], [
                new ImportChoice(EquipmentOwner::CLUB->value, 'club', ['club', 'association', 'aeroclub']),
                new ImportChoice(EquipmentOwner::PRIVATE->value, 'private', ['prive', 'privee', 'particulier', 'proprietaire']),
            ], EquipmentOwner::CLUB->value),
        ];
    }

    public function previewFields(): array
    {
        return $this->fields();
    }

    public function validate(array $values, ImportContext $context): ?string
    {
        if (mb_strlen($values['name']) > 255) {
            return 'importReasonTooLong';
        }
        $normalized = ColumnMapper::normalize($values['name']);
        if (isset($this->existingNames($context)[$normalized])) {
            return 'importReasonAlreadyExists';
        }
        if (!$context->markSeen('equipment:' . $normalized)) {
            return 'importReasonDuplicateInFile';
        }

        return null;
    }

    public function import(array $values, ImportContext $context): void
    {
        $equipment = (new Equipment())
            ->setName($values['name'])
            ->setType(EquipmentType::from($values['type']))
            ->setDescription($values['description'] !== '' ? $values['description'] : null)
            ->setOwner(EquipmentOwner::from($values['owner'] !== '' ? $values['owner'] : EquipmentOwner::CLUB->value))
            ->setClub($context->club)
            ->setCreatedBy($context->user)
            ->setActive(true);
        $this->entityManager->persist($equipment);
    }

    public function finalize(ImportContext $context): int
    {
        // Rien à faire après commit pour les équipements : pas d'effet de bord externe, donc
        // aucun échec possible.
        return 0;
    }

    /** @return array<string, true> noms actifs du club, normalisés */
    private function existingNames(ImportContext $context): array
    {
        return $context->remember('equipment.existingNames', function () use ($context): array {
            $names = [];
            foreach ($this->equipmentRepository->findActiveNamesByClub($context->club) as $name) {
                $names[ColumnMapper::normalize($name)] = true;
            }

            return $names;
        });
    }
}
