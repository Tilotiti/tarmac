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
                'label' => 'clubName',
                'attr' => [
                    'placeholder' => 'clubNamePlaceholder',
                    'class' => 'form-control',
                ],
            ])
            ->add('subdomain', TextType::class, [
                'label' => 'subdomain',
                'help' => 'subdomainHelp',
                'attr' => [
                    'placeholder' => 'subdomainPlaceholder',
                    'class' => 'form-control',
                ],
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'active',
                'help' => 'activeHelp',
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

