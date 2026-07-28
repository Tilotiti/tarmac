<?php

namespace App\Form\Filter;

use App\Entity\Club;
use App\Entity\Enum\AnomalyImpact;
use App\Entity\Enum\AnomalyStatus;
use App\Entity\Enum\EquipmentType;
use App\Entity\Equipment;
use App\Form\Type\StatusTagType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class AnomalyFilterType extends AbstractFilterType
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $club = $options['club'];

        $builder
            ->add('name', TextType::class, [
                'label' => 'anomalyName',
                'required' => false,
                'attr' => [
                    'placeholder' => 'searchByAnomalyName',
                ],
            ])
            ->add('equipment', EntityType::class, [
                'class' => Equipment::class,
                'choice_label' => 'name',
                'group_by' => function (Equipment $equipment) {
                    return $this->translator->trans($equipment->getType()->getLabel() . 'Type');
                },
                'label' => 'equipment',
                'required' => false,
                'placeholder' => 'all',
                'query_builder' => function ($er) use ($club) {
                    return $er->createQueryBuilder('e')
                        ->where('e.club = :club')
                        ->setParameter('club', $club)
                        ->orderBy('e.name', 'ASC');
                },
            ])
            ->add('equipmentType', EnumType::class, [
                'class' => EquipmentType::class,
                'label' => 'equipmentType',
                'choice_label' => fn (EquipmentType $type) => $type->getLabel(),
                'required' => false,
                'placeholder' => 'all',
            ])
            ->add('impact', StatusTagType::class, [
                'label' => 'anomalyImpact',
                'required' => false,
                'choices' => array_combine(
                    array_map(static fn (AnomalyImpact $impact) => $impact->getLabel(), AnomalyImpact::cases()),
                    array_map(static fn (AnomalyImpact $impact) => $impact->value, AnomalyImpact::cases()),
                ),
            ])
            ->add('status', StatusTagType::class, [
                'label' => 'status',
                'required' => false,
                'choices' => array_combine(
                    array_map(static fn (AnomalyStatus $status) => $status->getLabel(), AnomalyStatus::cases()),
                    array_map(static fn (AnomalyStatus $status) => $status->value, AnomalyStatus::cases()),
                ),
                'help' => 'anomalyStatusHelp',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'club' => null,
        ]);

        $resolver->setRequired('club');
        $resolver->setAllowedTypes('club', [Club::class, 'null']);
    }
}
