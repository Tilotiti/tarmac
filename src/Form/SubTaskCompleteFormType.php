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
use Symfony\Contracts\Translation\TranslatorInterface;

class SubTaskCompleteFormType extends AbstractType
{
    public function __construct(
        private readonly ClubResolver $clubResolver,
        private readonly MembershipRepository $membershipRepository,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
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
                'query_builder' => function (UserRepository $er) use ($club, $currentMembership, $isManager, $requiresPilot, $user) {
                    $qb = $er->createQueryBuilder('u')
                        ->join('u.memberships', 'm')
                        ->where('m.club = :club')
                        ->setParameter('club', $club);

                    if ($isManager && $user instanceof User) {
                        $qb->orderBy('CASE WHEN u.id = :doneBySortCurrentUser THEN 0 ELSE 1 END', 'ASC')
                            ->setParameter('doneBySortCurrentUser', $user->getId())
                            ->addOrderBy('u.lastname', 'ASC')
                            ->addOrderBy('u.firstname', 'ASC');
                    } else {
                        $qb->orderBy('u.lastname', 'ASC')
                            ->addOrderBy('u.firstname', 'ASC');
                    }

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
                'choice_label' => fn (User $user) => $user->getMemberChoiceLabel(),
                'label' => 'whoDidTask',
                'required' => true,
                'attr' => [
                    'class' => 'member-select form-select',
                    'data-controller' => 'member-select',
                    'data-member-select-placeholder-value' => $this->translator->trans('searchMember'),
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
