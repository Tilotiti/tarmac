<?php

namespace App\Form;

use App\Entity\Enum\EquipmentType;
use App\Entity\Equipment;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlanApplyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $equipmentType = $options['equipment_type'] ?? null;

        $builder
            ->add('equipment', EntityType::class, [
                'class' => Equipment::class,
                'choice_label' => 'name',
                'label' => 'equipment',
                'required' => true,
                'placeholder' => 'selectEquipment',
                'attr' => ['class' => 'form-select'],
                'query_builder' => function ($er) use ($equipmentType) {
                    /** @var QueryBuilder $qb */
                    $qb = $er->createQueryBuilder('e')
                        ->orderBy('e.name', 'ASC');

                    if ($equipmentType) {
                        $qb->andWhere('e.type = :type')
                            ->setParameter('type', $equipmentType);
                    }

                    return $qb;
                },
            ])
            ->add('dueAt', DateType::class, [
                'label' => 'dueDate',
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'help' => 'dueDateHelp',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // No data_class as this is a DTO form
            'equipment_type' => null,
        ]);

        $resolver->setAllowedTypes('equipment_type', ['null', EquipmentType::class]);
    }
}

