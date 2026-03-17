<?php

namespace App\Form\Filter;

use App\Form\Type\StatusTagType;
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
            ->add('status', StatusTagType::class, [
                'label' => 'status',
                'required' => false,
                'choices' => [
                    'statusOpenLabel' => 'open',
                    'statusDonePendingLabel' => 'done',
                    'statusClosedLabel' => 'closed',
                    'statusCancelledLabel' => 'cancelled',
                ],
                'help' => 'logbookStatusHelp',
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
}
