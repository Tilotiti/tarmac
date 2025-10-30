<?php

namespace App\Form;

use App\Entity\Club;
use App\Entity\Equipment;
use App\Entity\Enum\EquipmentType;
use App\Entity\Task;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
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
        $user = $options['user'];
        $club = $options['club'];
        $isEditMode = $options['is_edit'];

        // Determine if user is a pilot
        $isPilot = false;
        if ($user instanceof User && $club instanceof Club) {
            $membership = $user->getMembershipForClub($club);
            $isPilot = $membership?->isPilote() ?? false;
        }

        // Only show equipment field when creating a new task, not when editing
        if (!$isEditMode) {
            $builder
                ->add('equipment', EntityType::class, [
                    'class' => Equipment::class,
                    'choice_label' => 'name',
                    'group_by' => function (Equipment $equipment) {
                        return $this->translator->trans($equipment->getType()->value . 'Type');
                    },
                    'query_builder' => function (EntityRepository $er) use ($club, $isPilot) {
                        $qb = $er->createQueryBuilder('e')
                            ->where('e.club = :club')
                            ->andWhere('e.active = :active')
                            ->setParameter('club', $club)
                            ->setParameter('active', true)
                            ->orderBy('e.name', 'ASC');

                        // Non-pilots can only select facility equipment
                        if (!$isPilot) {
                            $qb->andWhere('e.type = :facilityType')
                                ->setParameter('facilityType', EquipmentType::FACILITY);
                        }

                        return $qb;
                    },
                    'label' => 'equipment',
                    'required' => true,
                    'attr' => ['class' => 'form-select'],
                ]);
        }

        $builder
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
            'is_edit' => false,
            'user' => null,
            'club' => null,
        ]);

        $resolver->setAllowedTypes('user', ['null', User::class]);
        $resolver->setAllowedTypes('club', ['null', Club::class]);
        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}

