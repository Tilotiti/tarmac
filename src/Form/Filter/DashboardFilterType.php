<?php

namespace App\Form\Filter;

use App\Entity\Enum\EquipmentType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;

class DashboardFilterType extends AbstractFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('equipmentType', EnumType::class, [
                'class' => EquipmentType::class,
                'label' => 'equipmentType',
                'choice_label' => fn(EquipmentType $type) => $type->getLabel(),
                'required' => false,
                'placeholder' => 'all',
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
        ;
    }
}

