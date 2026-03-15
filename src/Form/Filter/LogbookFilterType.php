<?php

namespace App\Form\Filter;

use App\Entity\Club;
use App\Entity\Equipment;
use App\Entity\Enum\EquipmentOwner;
use App\Entity\Enum\EquipmentType;
use App\Entity\Specialisation;
use App\Form\Type\SpecialisationTagType;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use App\Form\Type\StatusTagType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LogbookFilterType extends AbstractFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Club|null $club */
        $club = $options['club'];
        /** @var User|null $logbookOwner */
        $logbookOwner = $options['logbook_owner_user'];

        $builder
            ->add('search', TextType::class, [
                'label' => 'search',
                'required' => false,
                'attr' => [
                    'placeholder' => 'searchByTaskOrSubTaskName',
                ],
            ])
            ->add('specialisation', SpecialisationTagType::class, [
                'label' => 'specialisation',
                'required' => false,
                'club' => $club,
            ])
            ->add('equipmentType', EnumType::class, [
                'class' => EquipmentType::class,
                'label' => 'equipmentType',
                'choice_label' => fn (EquipmentType $type) => $type->getLabel(),
                'required' => false,
                'placeholder' => 'all',
            ])
            ->add('equipment', EntityType::class, [
                'class' => Equipment::class,
                'choice_label' => 'name',
                'label' => 'equipment',
                'required' => false,
                'placeholder' => 'all',
                'query_builder' => function ($er) use ($club, $logbookOwner) {
                    $qb = $er->createQueryBuilder('e');

                    if ($club) {
                        $qb
                            ->andWhere('e.club = :club')
                            ->setParameter('club', $club);
                    }

                    // Visibles : équipements du club + privés dont le propriétaire du carnet est dans owners
                    if ($logbookOwner) {
                        $qb
                            ->andWhere('(e.owner = :ownerClub OR :visibleUser MEMBER OF e.owners)')
                            ->setParameter('ownerClub', EquipmentOwner::CLUB)
                            ->setParameter('visibleUser', $logbookOwner);
                    }

                    return $qb->orderBy('e.name', 'ASC');
                },
            ])
            ->add('status', StatusTagType::class, [
                'label' => 'status',
                'required' => false,
                'choices' => [
                    'statusOpenLabel' => 'open',
                    'statusDonePendingLabel' => 'done',
                    'statusClosedLabel' => 'closed',
                    'statusCancelledLabel' => 'cancelled',
                ],
                'help' => 'logbookStatusHelp',
            ])
            ->add('periodStart', DateType::class, [
                'label' => 'periodStart',
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('periodEnd', DateType::class, [
                'label' => 'periodEnd',
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('signedOnly', CheckboxType::class, [
                'label' => 'signedOnlyFilter',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'club' => null,
            'logbook_owner_user' => null,
        ]);

        $resolver->setAllowedTypes('club', [Club::class, 'null']);
        $resolver->setAllowedTypes('logbook_owner_user', [User::class, 'null']);
    }
}

