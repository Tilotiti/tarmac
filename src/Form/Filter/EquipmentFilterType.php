<?php

namespace App\Form\Filter;

use App\Entity\Enum\EquipmentOwner;
use App\Entity\Enum\EquipmentType;
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
                'label' => 'search',
                'attr' => [
                    'placeholder' => 'searchByName',
                ],
            ])
            ->add('type', ChoiceType::class, [
                'required' => false,
                'label' => 'type',
                'placeholder' => 'allTypes',
                'choices' => [
                    'glider' => EquipmentType::GLIDER->value,
                    'infrastructure' => EquipmentType::FACILITY->value,
                ],
                'choice_translation_domain' => 'messages',
            ])
            ->add('owner', ChoiceType::class, [
                'required' => false,
                'label' => 'owner',
                'placeholder' => 'allOwners',
                'choices' => [
                    'club' => EquipmentOwner::CLUB->value,
                    'private' => EquipmentOwner::PRIVATE ->value,
                ],
                'choice_translation_domain' => 'messages',
            ])
        ;
    }
}

