<?php

namespace App\Form\Filter;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class MemberFilterType extends AbstractFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('search', TextType::class, [
                'label' => 'search',
                'required' => false,
                'attr' => [
                    'placeholder' => 'searchByNameOrEmail',
                ],
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'role',
                'required' => false,
                'placeholder' => 'allRoles',
                'choices' => [
                    'manager' => 'manager',
                    'inspector' => 'inspector',
                    'member' => 'member',
                ],
            ])
        ;
    }
}

