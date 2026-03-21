<?php

namespace App\Form;

use App\Entity\Club;
use App\Entity\Equipment;
use App\Entity\Enum\EquipmentOwner;
use App\Entity\Enum\EquipmentType as EquipmentTypeEnum;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class EquipmentType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Club|null $club */
        $club = $options['club'];

        $builder
            ->add('name', TextType::class, [
                'label' => 'name',
                'attr' => [
                    'placeholder' => 'equipmentNamePlaceholder',
                    'class' => 'form-control',
                ],
            ])
            ->add('type', EnumType::class, [
                'label' => 'type',
                'class' => EquipmentTypeEnum::class,
                'choice_label' => fn(EquipmentTypeEnum $type) => $type->getLabel(),
                'choice_translation_domain' => 'messages',
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('owner', EnumType::class, [
                'label' => 'owner',
                'class' => EquipmentOwner::class,
                'choice_label' => fn(EquipmentOwner $owner) => $owner->getLabel(),
                'choice_translation_domain' => 'messages',
                'expanded' => true,
            ])
            ->add('responsible', EntityType::class, [
                'class' => User::class,
                'query_builder' => function (EntityRepository $er) use ($club) {
                    return $er->createQueryBuilder('u')
                        ->innerJoin('u.memberships', 'm')
                        ->where('m.club = :club')
                        ->setParameter('club', $club)
                        ->orderBy('u.firstname', 'ASC')
                        ->addOrderBy('u.lastname', 'ASC');
                },
                'choice_label' => fn (User $user) => $user->getMemberChoiceLabel(),
                'label' => 'responsible',
                'required' => false,
                'placeholder' => 'selectResponsible',
                'attr' => [
                    'class' => 'member-select form-select',
                    'data-controller' => 'member-select',
                    'data-member-select-placeholder-value' => $this->translator->trans('searchMember'),
                    'data-member-select-clearable-value' => 'true',
                ],
            ])
            ->add('owners', EntityType::class, [
                'class' => User::class,
                'query_builder' => function (EntityRepository $er) use ($club) {
                    return $er->createQueryBuilder('u')
                        ->innerJoin('u.memberships', 'm')
                        ->where('m.club = :club')
                        ->setParameter('club', $club)
                        ->orderBy('u.firstname', 'ASC')
                        ->addOrderBy('u.lastname', 'ASC');
                },
                'choice_label' => fn (User $user) => $user->getMemberChoiceLabel(),
                'label' => 'owners',
                'multiple' => true,
                'required' => false,
                'attr' => [
                    'class' => 'member-select form-select',
                    'data-controller' => 'member-select',
                    'data-member-select-placeholder-value' => $this->translator->trans('searchMember'),
                    'data-member-select-multiple-value' => 'true',
                    'data-action' => 'change->equipment-privacy#updateCounter',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Equipment::class,
            'club' => null,
        ]);

        $resolver->setRequired('club');
        $resolver->setAllowedTypes('club', [Club::class, 'null']);
    }
}

