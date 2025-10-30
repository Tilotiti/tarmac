<?php

namespace App\Form\Filter;

use App\Entity\Club;
use App\Entity\Equipment;
use App\Entity\Enum\EquipmentType;
use App\Entity\Plan;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlanApplicationFilterType extends AbstractFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $club = $options['club'];

        $builder
            ->add('plan', EntityType::class, [
                'class' => Plan::class,
                'choice_label' => 'name',
                'label' => 'plan',
                'required' => false,
                'placeholder' => 'all',
                'attr' => ['class' => 'form-select'],
                'query_builder' => function ($er) use ($club) {
                    return $er->createQueryBuilder('p')
                        ->where('p.club = :club')
                        ->setParameter('club', $club)
                        ->orderBy('p.name', 'ASC');
                },
            ])
            ->add('equipment', EntityType::class, [
                'class' => Equipment::class,
                'choice_label' => 'name',
                'label' => 'equipment',
                'required' => false,
                'placeholder' => 'all',
                'attr' => ['class' => 'form-select'],
                'query_builder' => function ($er) use ($club) {
                    return $er->createQueryBuilder('e')
                        ->where('e.club = :club')
                        ->setParameter('club', $club)
                        ->orderBy('e.name', 'ASC');
                },
            ])
            ->add('equipmentType', EnumType::class, [
                'class' => EquipmentType::class,
                'label' => 'equipmentType',
                'choice_label' => fn(EquipmentType $type) => $type->getLabel(),
                'required' => false,
                'placeholder' => 'all',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('cancelled', ChoiceType::class, [
                'label' => 'status',
                'choices' => [
                    'all' => '',
                    'active' => '0',
                    'cancelled' => '1',
                ],
                'required' => false,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('dueDate', ChoiceType::class, [
                'label' => 'dueDate',
                'choices' => [
                    'all' => 'all',
                    'endOfWeek' => 'end_of_week',
                    'endOfMonth' => 'end_of_month',
                ],
                'required' => false,
                'data' => 'all',
                'attr' => ['class' => 'form-select'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        
        $resolver->setDefaults([
            'club' => null,
        ]);

        $resolver->setRequired('club');
        $resolver->setAllowedTypes('club', [Club::class, 'null']);
    }
}

