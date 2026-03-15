<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Tag-style selector for simple status filters, reusing the specialisation tag widget.
 */
class StatusTagType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'multiple' => true,
            'expanded' => false,
            'required' => false,
            'attr' => ['class' => 'd-none'],
        ]);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function getBlockPrefix(): string
    {
        // Reuse the specialisation_tag_widget block so the UI matches specialisation tags
        return 'specialisation_tag';
    }
}

