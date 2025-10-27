<?php

namespace App\Form;

use App\Entity\Equipment;
use App\Entity\Task;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class TaskType extends AbstractType
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('equipment', EntityType::class, [
                'class' => Equipment::class,
                'choice_label' => 'name',
                'group_by' => function (Equipment $equipment) {
                    return $this->translator->trans($equipment->getType()->value . 'Type');
                },
                'label' => 'equipment',
                'required' => true,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('title', TextType::class, [
                'label' => 'title',
                'required' => true,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'description',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 4],
            ])
            ->add('dueAt', DateType::class, [
                'label' => 'dueDate',
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
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

        // Only include sub-tasks for new tasks, not for editing
        if ($options['include_subtasks']) {
            $builder->add('subTasks', CollectionType::class, [
                'entry_type' => SubTaskType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => false,
                'prototype' => true,
                'prototype_name' => '__subtask_name__',
                'attr' => [
                    'data-controller' => 'collection-type',
                    'data-collection-type-prototype-name-value' => '__subtask_name__',
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Task::class,
            'include_subtasks' => false,
        ]);
    }
}

