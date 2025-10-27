<?php

namespace App\Form\Filter;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class ClubFilterType extends AbstractFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'name',
                'required' => false,
                'attr' => [
                    'placeholder' => 'searchByName',
                    'class' => 'form-control',
                ],
                'label_attr' => [
                    'class' => 'form-label',
                ],
            ])
            ->add('subdomain', TextType::class, [
                'label' => 'subdomain',
                'required' => false,
                'attr' => [
                    'placeholder' => 'searchBySubdomain',
                    'class' => 'form-control',
                ],
                'label_attr' => [
                    'class' => 'form-label',
                ],
            ])
            ->add('active', ChoiceType::class, [
                'label' => 'status',
                'choices' => [
                    'all' => '',
                    'active' => '1',
                    'inactive' => '0',
                ],
                'required' => false,
                'attr' => [
                    'class' => 'form-select',
                ],
                'label_attr' => [
                    'class' => 'form-label',
                ],
            ])
        ;
    }
}

