<?php

namespace App\Form;

use App\Entity\Enum\EquipmentType;
use App\Entity\Plan;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlanType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'name',
                'required' => true,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'description',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 4],
            ])
            ->add('equipmentType', EnumType::class, [
                'class' => EquipmentType::class,
                'label' => 'equipmentType',
                'choice_label' => fn(EquipmentType $type) => $type->getLabel(),
                'required' => true,
                'attr' => ['class' => 'form-select'],
            ])
        ;

        if ($options['include_task_templates']) {
            $builder->add('taskTemplates', CollectionType::class, [
                'entry_type' => PlanTaskType::class,
                'entry_options' => [
                    'label' => false,
                    'club' => $options['club'],
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => 'tasks',
                'attr' => ['class' => 'task-templates-collection'],
            ]);
        }

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            if (!$form->getConfig()->getOption('include_task_templates')) {
                return;
            }

            $data = $event->getData();
            if (!\is_array($data) || !isset($data['taskTemplates']) || !\is_array($data['taskTemplates'])) {
                return;
            }

            $filteredTasks = [];
            foreach ($data['taskTemplates'] as $taskData) {
                if (!\is_array($taskData)) {
                    continue;
                }
                if (trim((string) ($taskData['title'] ?? '')) === '') {
                    continue;
                }
                if (isset($taskData['subTaskTemplates']) && \is_array($taskData['subTaskTemplates'])) {
                    $taskData['subTaskTemplates'] = array_values(array_filter(
                        $taskData['subTaskTemplates'],
                        static fn ($subData): bool => \is_array($subData)
                            && trim((string) ($subData['title'] ?? '')) !== '',
                    ));
                }
                $filteredTasks[] = $taskData;
            }
            $data['taskTemplates'] = $filteredTasks;
            $event->setData($data);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Plan::class,
            'club' => null,
            'include_task_templates' => true,
        ]);
        $resolver->setAllowedTypes('club', ['null', \App\Entity\Club::class]);
        $resolver->setAllowedTypes('include_task_templates', 'bool');
    }
}

