<?php

namespace App\Form\Filter;

use App\Entity\Enum\EquipmentType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class PlanFilterType extends AbstractFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('search', TextType::class, [
                'required' => false,
                'label' => 'search',
                'attr' => [
                    'placeholder' => 'searchPlans',
                ],
            ])
            ->add('equipmentType', ChoiceType::class, [
                'required' => false,
                'label' => 'equipmentType',
                'placeholder' => 'allEquipmentTypes',
                'choices' => [
                    'gliderType' => EquipmentType::GLIDER->value,
                    'airplaneType' => EquipmentType::AIRPLANE->value,
                    'infrastructureType' => EquipmentType::FACILITY->value,
                ],
                'choice_translation_domain' => 'messages',
            ])
        ;
    }
}

