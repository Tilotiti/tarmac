<?php

namespace App\Form\Filter;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Base filter type that provides common options for all filter forms.
 * 
 * This ensures consistency across all filter types and provides
 * support for default filter values via the 'defaults' option.
 */
abstract class AbstractFilterType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
            'method' => 'GET',
            'allow_extra_fields' => true,
            'defaults' => [],
        ]);
    }
}





