<?php

namespace App\Form;

use App\Entity\SubTask;
use App\Entity\User;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use App\Service\ClubResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SubTaskCompleteFormType extends AbstractType
{
    public function __construct(
        private readonly ClubResolver $clubResolver,
        private readonly MembershipRepository $membershipRepository,
        private readonly Security $security,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $club = $this->clubResolver->resolve();
        $user = $this->security->getUser();
        $currentMembership = $user && $club
            ? $this->membershipRepository->findOneBy(['user' => $user, 'club' => $club])
            : null;
        $isManager = $this->security->isGranted('MANAGE');

        /** @var SubTask|null $subTask */
        $subTask = $options['subtask'];
        $requiresPilot = $subTask && $subTask->getTask()->getEquipment()->getType()->isAircraft();

        $builder
            ->add('doneBy', EntityType::class, [
                'class' => User::class,
                'data' => $options['initial_done_by'],
                'query_builder' => function (UserRepository $er) use ($club, $currentMembership, $isManager, $requiresPilot) {
                    $qb = $er->createQueryBuilder('u')
                        ->join('u.memberships', 'm')
                        ->where('m.club = :club')
                        ->setParameter('club', $club)
                        ->orderBy('u.lastname', 'ASC')
                        ->addOrderBy('u.firstname', 'ASC');

                    if ($requiresPilot) {
                        $qb->andWhere('m.isPilote = :true')
                            ->setParameter('true', true);
                    }

                    if (!$isManager && $currentMembership) {
                        $qb->andWhere('u.id = :userId')
                            ->setParameter('userId', $currentMembership->getUser()->getId());
                    }

                    return $qb;
                },
                'choice_label' => fn (User $user) => $user->getLastname() . ' ' . $user->getFirstname() . ' (' . $user->getEmail() . ')',
                'label' => 'whoDidTask',
                'required' => true,
                'expanded' => true,
                'attr' => [
                    'class' => 'form-check-input',
                ],
            ])
            ->add('contributions', CollectionType::class, [
                'entry_type' => ContributionItemType::class,
                'entry_options' => [
                    'club' => $club,
                    'current_membership' => $currentMembership,
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'label' => 'contributions',
                'by_reference' => false,
                'prototype' => true,
                'prototype_name' => '__name__',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SubTask::class,
            'subtask' => null,
            'initial_done_by' => null,
        ]);
        $resolver->setAllowedTypes('subtask', [SubTask::class, 'null']);
        $resolver->setAllowedTypes('initial_done_by', [User::class, 'null']);
    }
}
