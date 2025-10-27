<?php

namespace App\Form\Filter;

use App\Entity\Equipment;
use App\Entity\Enum\EquipmentType;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaskFilterType extends AbstractFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('equipment', EntityType::class, [
                'class' => Equipment::class,
                'choice_label' => 'name',
                'label' => 'equipment',
                'required' => false,
                'placeholder' => 'all',
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
                    'facile' => 2,
                    'moyen' => 3,
                    'difficile' => 4,
                    'expert' => 5,
                ],
                'required' => false,
            ])
            ->add('requiresInspection', ChoiceType::class, [
                'label' => 'requiresInspection',
                'choices' => [
                    'all' => '',
                    'yes' => '1',
                    'no' => '0',
                ],
                'required' => false,
            ])
            ->add('awaitingInspection', ChoiceType::class, [
                'label' => 'awaitingInspection',
                'choices' => [
                    'all' => '',
                    'yes' => '1',
                ],
                'required' => false,
            ])
            ->add('claimedBy', EntityType::class, [
                'class' => User::class,
                'choice_label' => fn(User $user) => $user->getFullName() ?? $user->getEmail(),
                'label' => 'claimedBy',
                'required' => false,
                'placeholder' => 'all',
            ])
        ;
    }
}

