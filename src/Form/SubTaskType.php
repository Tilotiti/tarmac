<?php

namespace App\Form;

use App\Entity\SubTask;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SubTaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'title',
                'required' => true,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'description',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3],
            ])
            ->add('difficulty', ChoiceType::class, [
                'label' => 'difficulty',
                'choices' => [
                    'debutant' => 1,
                    'facile' => 2,
                    'moyen' => 3,
                    'difficile' => 4,
                    'expert' => 5,
                ],
                'required' => true,
                'data' => 3,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('requiresInspection', CheckboxType::class, [
                'label' => 'requiresInspection',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SubTask::class,
        ]);
    }
}

