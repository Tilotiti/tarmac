<?php

namespace App\Form\Filter;

use App\Entity\Club;
use App\Entity\Equipment;
use App\Entity\Enum\EquipmentType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaskFilterType extends AbstractFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $club = $options['club'];

        $builder
            ->add('equipment', EntityType::class, [
                'class' => Equipment::class,
                'choice_label' => 'name',
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
                'choice_label' => fn(EquipmentType $type) => $type->getLabel(),
                'required' => false,
                'placeholder' => 'all',
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'status',
                'choices' => [
                    'all' => '',
                    'open' => 'open',
                    'awaitingInspection' => 'awaitingInspection',
                    'closed' => 'closed',
                    'cancelled' => 'cancelled',
                ],
                'required' => false
            ])
            ->add('dueDate', ChoiceType::class, [
                'label' => 'dueDate',
                'choices' => [
                    'all' => '',
                    'endOfWeek' => 'end_of_week',
                    'endOfMonth' => 'end_of_month',
                ],
                'required' => false,
                'placeholder' => 'all',
            ])
            ->add('difficulty', ChoiceType::class, [
                'label' => 'difficulty',
                'choices' => [
                    'all' => '',
                    'debutant' => 1,
                    'experimente' => 2,
                    'expert' => 3,
                ],
                'required' => false,
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

