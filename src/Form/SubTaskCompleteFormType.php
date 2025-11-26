<?php

namespace App\Form;

use App\Entity\Club;
use App\Entity\Enum\EquipmentType;
use App\Entity\Membership;
use App\Entity\SubTask;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class SubTaskCompleteFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Club|null $club */
        $club = $options['club'];
        /** @var Membership|null $currentMembership */
        $currentMembership = $options['current_membership'];
        $isManager = $options['is_manager'];
        /** @var SubTask|null $subTask */
        $subTask = $options['subtask'];

        // Determine if we need to filter by pilot status
        $requiresPilot = false;
        if ($subTask) {
            $equipment = $subTask->getTask()->getEquipment();
            $requiresPilot = $equipment->getType()->isAircraft();
        }

        $builder
            ->add('doneBy', EntityType::class, [
                'class' => Membership::class,
                'query_builder' => function (EntityRepository $er) use ($club, $currentMembership, $isManager, $requiresPilot) {
                    $qb = $er->createQueryBuilder('m')
                        ->join('m.user', 'u')
                        ->where('m.club = :club')
                        ->setParameter('club', $club)
                        ->orderBy('u.firstname', 'ASC')
                        ->addOrderBy('u.lastname', 'ASC');

                    // Only filter by pilot status for aircraft equipment
                    if ($requiresPilot) {
                        $qb->andWhere('m.isPilote = :true')
                            ->setParameter('true', true);
                    }

                    // If not manager, only show current user's membership
                    if (!$isManager && $currentMembership) {
                        $qb->andWhere('m.id = :currentMembership')
                            ->setParameter('currentMembership', $currentMembership->getId());
                    }

                    return $qb;
                },
                'choice_label' => function (Membership $membership) {
                    $user = $membership->getUser();
                    return $user->getFullName() ?: $user->getEmail();
                },
                'label' => 'whoDidTask',
                'required' => true,
                'expanded' => true,
                'data' => $currentMembership,
                'attr' => [
                    'class' => 'form-check-input',
                ],
            ])
            ->add('timeSpent', IntegerType::class, [
                'label' => 'timeSpent',
                'required' => true,
                'data' => 1,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                    'placeholder' => 'timeSpentPlaceholder',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'timeSpentRequired'),
                    new Assert\Positive(message: 'timeSpentMustBePositive'),
                ],
                'help' => 'timeSpentHelp',
            ])
            ->add('contributors', EntityType::class, [
                'class' => Membership::class,
                'query_builder' => function (EntityRepository $er) use ($club) {
                    return $er->createQueryBuilder('m')
                        ->join('m.user', 'u')
                        ->where('m.club = :club')
                        ->setParameter('club', $club)
                        ->orderBy('u.lastname', 'ASC')
                        ->addOrderBy('u.firstname', 'ASC');
                },
                'choice_label' => function (Membership $membership) {
                    $user = $membership->getUser();
                    return $user->getFullName() ?: $user->getEmail();
                },
                'label' => 'contributors',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'data' => $currentMembership ? [$currentMembership] : [],
                'attr' => [
                    'class' => 'form-check-input',
                ],
                'help' => 'contributorsHelp',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'club' => null,
            'current_membership' => null,
            'is_manager' => false,
            'subtask' => null,
        ]);

        $resolver->setRequired(['club', 'current_membership', 'is_manager']);
        $resolver->setAllowedTypes('club', [Club::class, 'null']);
        $resolver->setAllowedTypes('current_membership', [Membership::class, 'null']);
        $resolver->setAllowedTypes('is_manager', 'bool');
        $resolver->setAllowedTypes('subtask', [SubTask::class, 'null']);
    }
}

