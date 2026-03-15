<?php

namespace App\Form;

use App\Entity\PlanSubTask;
use App\Form\Type\SpecialisationTagType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class PlanSubTaskType extends AbstractType
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
                'attr' => ['class' => 'form-control', 'rows' => 2],
            ])
            ->add('difficulty', ChoiceType::class, [
                'label' => 'difficulty',
                'choices' => [
                    'debutant' => 1,
                    'experimente' => 2,
                    'expert' => 3,
                ],
                'required' => true,
                'data' => 2,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('requiresInspection', CheckboxType::class, [
                'label' => 'requiresInspection',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('documentation', FileType::class, [
                'label' => 'planSubTaskDocumentation',
                'required' => false,
                'mapped' => false,
                'upload' => 'documentation',
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/*,.pdf,application/pdf',
                ],
                'constraints' => [
                    new Assert\File([
                        'maxSize' => '10M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'image/webp',
                            'application/pdf',
                        ],
                        'mimeTypesMessage' => 'invalidFileFormat',
                    ]),
                ],
            ])
        ;

        $club = $options['club'];
        if ($club !== null) {
            $builder->add('specialisations', SpecialisationTagType::class, [
                'label' => 'specialisations',
                'club' => $club,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlanSubTask::class,
            'club' => null,
        ]);
        $resolver->setAllowedTypes('club', ['null', \App\Entity\Club::class]);
    }
}

