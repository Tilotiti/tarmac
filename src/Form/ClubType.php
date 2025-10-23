<?php

namespace App\Form;

use App\Entity\Club;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClubType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du club',
                'attr' => [
                    'placeholder' => 'Ex: Les Planeurs de Bailleau',
                    'class' => 'form-control',
                ],
            ])
            ->add('subdomain', TextType::class, [
                'label' => 'Sous-domaine',
                'help' => 'Ce sous-domaine sera utilisé pour accéder au club (ex: cvve)',
                'attr' => [
                    'placeholder' => 'Ex: cvve',
                    'class' => 'form-control',
                ],
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Actif',
                'help' => 'Un club inactif ne sera pas accessible',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Club::class,
        ]);
    }
}

