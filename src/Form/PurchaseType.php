<?php

namespace App\Form;

use App\Entity\Purchase;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class PurchaseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'purchaseName',
                'required' => true,
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'nameRequired',
                    ]),
                ],
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'purchaseQuantity',
                'required' => true,
                'attr' => ['class' => 'form-control', 'min' => 1],
                'constraints' => [
                    new Assert\NotNull(),
                    new Assert\GreaterThanOrEqual(1),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'purchaseDescription',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 4],
            ])
            ->add('requestImage', FileType::class, [
                'label' => 'purchaseRequestImage',
                'required' => false,
                'mapped' => false,
                'upload' => 'purchase',
                'attr' => ['class' => 'form-control', 'accept' => 'image/*'],
            ])
            ->add('billImage', FileType::class, [
                'label' => 'purchaseBillImage',
                'required' => false,
                'mapped' => false,
                'upload' => 'bill',
                'attr' => ['class' => 'form-control', 'accept' => 'image/*,.pdf,application/pdf'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Purchase::class,
        ]);
    }
}

