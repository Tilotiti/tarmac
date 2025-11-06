<?php

namespace App\Form;

use App\Entity\Club;
use App\Entity\Membership;
use App\Entity\Purchase;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class PurchasePurchasedType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $club = $options['club'];
        $isManager = $options['is_manager'];
        $currentMembership = $options['current_membership'];

        $builder
            ->add('requestImage', FileType::class, [
                'label' => 'purchaseRequestImageOrPdf',
                'required' => false,
                'mapped' => false,
                'upload' => 'purchase',
                'attr' => ['class' => 'form-control', 'accept' => 'image/*,.pdf,application/pdf'],
                'constraints' => [
                    new Assert\File([
                        'maxSize' => '10M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'image/webp',
                            'application/pdf',
                        ],
                        'mimeTypesMessage' => 'invalidFileFormat',
                    ]),
                ],
            ])
            ->add('billImage', FileType::class, [
                'label' => 'purchaseBillImageOrPdf',
                'required' => false,
                'mapped' => false,
                'upload' => 'bill',
                'attr' => ['class' => 'form-control', 'accept' => 'image/*,.pdf,application/pdf'],
                'constraints' => [
                    new Assert\File([
                        'maxSize' => '10M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'image/webp',
                            'application/pdf',
                        ],
                        'mimeTypesMessage' => 'invalidFileFormat',
                    ]),
                ],
            ])
        ;

        // Only show purchasedBy field if user is a manager
        if ($isManager) {
            $builder->add('purchasedByMembership', EntityType::class, [
                'class' => Membership::class,
                'query_builder' => function (EntityRepository $er) use ($club) {
                    return $er->createQueryBuilder('m')
                        ->join('m.user', 'u')
                        ->where('m.club = :club')
                        ->setParameter('club', $club)
                        ->orderBy('u.firstname', 'ASC')
                        ->addOrderBy('u.lastname', 'ASC');
                },
                'choice_label' => function (Membership $membership) {
                    $user = $membership->getUser();
                    return $user->getFullName() ?: $user->getEmail();
                },
                'label' => 'purchasedBy',
                'required' => true,
                'expanded' => true,
                'mapped' => false,
                'data' => $currentMembership,
                'attr' => [
                    'class' => 'form-check-input',
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Purchase::class,
            'club' => null,
            'is_manager' => false,
            'current_membership' => null,
        ]);

        $resolver->setAllowedTypes('club', [Club::class, 'null']);
        $resolver->setAllowedTypes('is_manager', 'bool');
        $resolver->setAllowedTypes('current_membership', [Membership::class, 'null']);
    }
}

