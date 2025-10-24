<?php

namespace App\Form\Filter;

use App\Entity\EquipmentType as EquipmentTypeEnum;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class EquipmentFilterType extends AbstractFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('search', TextType::class, [
                'required' => false,
                'label' => 'Rechercher',
                'attr' => [
                    'placeholder' => 'Nom de l\'équipement',
                ],
            ])
            ->add('type', ChoiceType::class, [
                'required' => false,
                'label' => 'Type',
                'choices' => [
                    'Tous les types' => '',
                    'Planeur' => EquipmentTypeEnum::GLIDER->value,
                    'Infrastructure' => EquipmentTypeEnum::FACILITY->value,
                ],
            ])
        ;
    }
}

