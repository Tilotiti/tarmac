<?php

namespace App\Form\Filter;

use App\Entity\User;
use App\Form\Type\TagFilterType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PurchaseFilterType extends AbstractFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('status', TagFilterType::class, [
                'label' => 'purchaseStatus',
                'choices' => [
                    'purchaseRequested' => 'requested',
                    'purchasePendingApproval' => 'pending_approval',
                    'purchaseApproved' => 'approved',
                    'purchasePurchased' => 'purchased',
                    'purchaseDelivered' => 'delivered',
                    'purchaseComplete' => 'complete',
                    'purchaseReimbursed' => 'reimbursed',
                    'purchaseCancelled' => 'cancelled',
                ],
                'required' => false,
            ])
            ->add('ownership', ChoiceType::class, [
                'label' => 'ownership',
                'choices' => [
                    'myRequests' => 'myRequests',
                    'myPurchases' => 'myPurchases',
                ],
                'required' => false,
                'placeholder' => 'all',
                'attr' => ['class' => 'form-select'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'user' => null,
        ]);

        $resolver->setAllowedTypes('user', ['null', User::class]);
    }
}

