<?php

namespace App\Form;

use App\Entity\Club;
use App\Entity\Contribution;
use App\Entity\Membership;
use App\Form\TimeSpentType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class ContributionItemType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Club $club */
        $club = $options['club'];
        /** @var Membership|null $currentMembership */
        $currentMembership = $options['current_membership'];

        $membershipFieldOptions = [
            'class' => Membership::class,
            'query_builder' => function (EntityRepository $er) use ($club, $currentMembership) {
                $qb = $er->createQueryBuilder('m')
                    ->join('m.user', 'u')
                    ->where('m.club = :club')
                    ->setParameter('club', $club)
                    ->orderBy('u.lastname', 'ASC')
                    ->addOrderBy('u.firstname', 'ASC');

                // Put current user first in new contribution selects.
                if ($currentMembership instanceof Membership) {
                    $qb->addSelect('CASE WHEN m.id = :currentMembershipId THEN 0 ELSE 1 END AS HIDDEN current_first')
                        ->setParameter('currentMembershipId', $currentMembership->getId())
                        ->orderBy('current_first', 'ASC')
                        ->addOrderBy('u.lastname', 'ASC')
                        ->addOrderBy('u.firstname', 'ASC');
                }

                return $qb;
            },
            'choice_label' => fn (Membership $membership) => $membership->getUser()->getMemberChoiceLabel(),
            'label' => false,
            'attr' => [
                'class' => 'member-select form-select',
                'data-controller' => 'member-select',
                'data-member-select-placeholder-value' => $this->translator->trans('searchMember'),
            ],
        ];
        $builder
            ->add('membership', EntityType::class, $membershipFieldOptions)
            ->add('timeSpent', TimeSpentType::class, [
                'label' => false,
            ])
        ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($membershipFieldOptions): void {
            $data = $event->getData();
            if ($data instanceof Contribution && null !== $data->getId()) {
                $form = $event->getForm();
                $form->remove('membership');
                $form->add('membership', EntityType::class, array_merge($membershipFieldOptions, ['disabled' => true]));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Contribution::class,
            'current_membership' => null,
        ]);
        $resolver->setRequired(['club']);
        $resolver->setAllowedTypes('club', Club::class);
        $resolver->setAllowedTypes('current_membership', [Membership::class, 'null']);
    }
}
