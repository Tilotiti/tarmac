<?php

namespace App\Form;

use App\Entity\Anomaly;
use App\Entity\Club;
use App\Entity\Enum\AnomalyImpact;
use App\Entity\Enum\EquipmentOwner;
use App\Entity\Equipment;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class AnomalyType extends AbstractType
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var User|null $user */
        $user = $options['user'];
        /** @var Club|null $club */
        $club = $options['club'];
        $canManage = $options['can_manage'];
        $isEditMode = $options['is_edit'];

        $membership = ($user instanceof User && $club instanceof Club)
            ? $user->getMembershipForClub($club)
            : null;
        // Private equipment is only listed for managers, inspectors and its owners
        $seesAllEquipment = ($membership?->isManager() ?? false) || ($membership?->isInspector() ?? false);

        // The equipment is fixed once the anomaly exists, or when it comes from
        // the equipment page ("report an anomaly on this glider").
        if (!$isEditMode && !$options['lock_equipment']) {
            $builder->add('equipment', EntityType::class, [
                'class' => Equipment::class,
                'choice_label' => 'name',
                'group_by' => function (Equipment $equipment) {
                    return $this->translator->trans($equipment->getType()->getLabel() . 'Type');
                },
                'query_builder' => function (EntityRepository $er) use ($club, $seesAllEquipment, $user) {
                    $qb = $er->createQueryBuilder('e')
                        ->where('e.club = :club')
                        ->andWhere('e.active = :active')
                        ->setParameter('club', $club)
                        ->setParameter('active', true)
                        ->orderBy('e.name', 'ASC');

                    if (!$seesAllEquipment && $user instanceof User) {
                        $qb->andWhere('e.owner != :privateOwner OR :currentUser MEMBER OF e.owners')
                            ->setParameter('privateOwner', EquipmentOwner::PRIVATE)
                            ->setParameter('currentUser', $user);
                    }

                    return $qb;
                },
                'label' => 'equipment',
                'required' => true,
                'attr' => ['class' => 'form-select'],
            ]);
        }

        // Only managers can attribute an anomaly to another member
        if ($canManage) {
            $builder->add('reportedBy', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'fullName',
                'label' => 'anomalyReportedBy',
                'help' => 'anomalyReportedByHelp',
                'required' => true,
                'query_builder' => function (UserRepository $er) use ($club, $user) {
                    $qb = $er->createQueryBuilder('u')
                        ->join('u.memberships', 'm')
                        ->where('m.club = :club')
                        ->setParameter('club', $club);

                    if ($user instanceof User) {
                        $qb->orderBy('CASE WHEN u.id = :currentUserId THEN 0 ELSE 1 END', 'ASC')
                            ->setParameter('currentUserId', $user->getId())
                            ->addOrderBy('u.lastname', 'ASC')
                            ->addOrderBy('u.firstname', 'ASC');
                    } else {
                        $qb->orderBy('u.lastname', 'ASC')
                            ->addOrderBy('u.firstname', 'ASC');
                    }

                    return $qb;
                },
                'attr' => ['class' => 'form-select', 'data-controller' => 'member-select'],
            ]);
        }

        $builder
            ->add('reportedAt', DateType::class, [
                'label' => 'anomalyReportedAt',
                'required' => true,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('title', TextType::class, [
                'label' => 'title',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'anomalyTitlePlaceholder',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'description',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 5,
                    'placeholder' => 'anomalyDescriptionPlaceholder',
                ],
            ])
        ;

        // The impact is proposed by the reporter, then only managers may revise it
        if (!$isEditMode || $canManage) {
            $builder->add('impact', EnumType::class, [
                'class' => AnomalyImpact::class,
                'label' => 'anomalyImpact',
                'help' => 'anomalyImpactHelp',
                'choice_label' => fn (AnomalyImpact $impact) => $impact->getLabel(),
                'required' => true,
                'expanded' => true,
            ]);
        }

        // Les photos ne passent pas par le formulaire : voir AnomalyController::collectPhotos()
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Anomaly::class,
            'is_edit' => false,
            'lock_equipment' => false,
            'can_manage' => false,
            'user' => null,
            'club' => null,
        ]);

        $resolver->setAllowedTypes('user', ['null', User::class]);
        $resolver->setAllowedTypes('club', ['null', Club::class]);
        $resolver->setAllowedTypes('is_edit', 'bool');
        $resolver->setAllowedTypes('lock_equipment', 'bool');
        $resolver->setAllowedTypes('can_manage', 'bool');
    }
}
