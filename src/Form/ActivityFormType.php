<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ActivityFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $constraints = [];
        
        if ($options['required']) {
            $constraints[] = new Assert\NotBlank(['message' => 'messageRequired']);
        }

        $builder
            ->add('message', TextareaType::class, [
                'label' => $options['label'],
                'required' => $options['required'],
                'constraints' => $constraints,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => $options['rows'],
                    'placeholder' => $options['placeholder'],
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'required' => false,
            'label' => 'message',
            'rows' => 3,
            'placeholder' => '',
        ]);

        $resolver->setAllowedTypes('required', 'bool');
        $resolver->setAllowedTypes('label', 'string');
        $resolver->setAllowedTypes('rows', 'int');
        $resolver->setAllowedTypes('placeholder', 'string');
    }
}

