<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WelcomeMessageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('welcomeMessage', TextareaType::class, [
                'label' => 'welcomeMessage',
                'help' => 'welcomeMessageHelp',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 5,
                    'placeholder' => 'welcomeMessagePlaceholder',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}

