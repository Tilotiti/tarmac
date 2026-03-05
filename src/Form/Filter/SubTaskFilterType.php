<?php

namespace App\Form\Filter;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class SubTaskFilterType extends AbstractFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('search', TextType::class, [
                'label' => 'search',
                'required' => false,
                'attr' => [
                    'placeholder' => 'search',
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'status',
                'required' => false,
                'placeholder' => 'all',
                'choices' => [
                    'open' => 'open',
                    'closed' => 'closed',
                    'cancelled' => 'cancelled',
                ],
            ])
        ;
    }
}
