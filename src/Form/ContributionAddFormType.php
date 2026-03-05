<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContributionAddFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('timeSpent', TimeSpentType::class, [
            'label' => false,
            'default_hours' => $options['default_hours'],
            'default_minutes' => $options['default_minutes'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'default_hours' => 1,
            'default_minutes' => 0,
        ]);
    }
}
