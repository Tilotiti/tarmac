<?php

namespace App\Form;

use App\Entity\Invitation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class InvitationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'email',
                'disabled' => $options['disable_email'],
                'attr' => [
                    'placeholder' => 'emailPlaceholder',
                ],
            ])
            ->add('firstname', TextType::class, [
                'label' => 'firstname',
                'required' => false,
                'attr' => [
                    'placeholder' => 'firstnamePlaceholder',
                ],
            ])
            ->add('lastname', TextType::class, [
                'label' => 'lastname',
                'required' => false,
                'attr' => [
                    'placeholder' => 'lastnamePlaceholder',
                ],
            ])
            ->add('isManager', CheckboxType::class, [
                'label' => 'manager',
                'required' => false,
                'help' => 'managerHelp',
            ])
            ->add('isInspector', CheckboxType::class, [
                'label' => 'inspector',
                'required' => false,
                'help' => 'inspectorHelp',
            ])
            ->add('isPilote', CheckboxType::class, [
                'label' => 'pilot',
                'required' => false,
                'help' => 'pilotHelp',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Invitation::class,
            'disable_email' => false,
        ]);
    }
}





