<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Email;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstname', TextType::class, [
                'label' => 'firstname',
                'attr' => [
                    'placeholder' => 'firstnamePlaceholder',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'firstnameRequired',
                    ]),
                    new Length([
                        'min' => 2,
                        'max' => 50,
                        'minMessage' => 'firstnameMinLength',
                        'maxMessage' => 'firstnameMaxLength',
                    ]),
                ],
            ])
            ->add('lastname', TextType::class, [
                'label' => 'lastname',
                'attr' => [
                    'placeholder' => 'lastnamePlaceholder',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'lastnameRequired',
                    ]),
                    new Length([
                        'min' => 2,
                        'max' => 50,
                        'minMessage' => 'lastnameMinLength',
                        'maxMessage' => 'lastnameMaxLength',
                    ]),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'email',
                'attr' => [
                    'placeholder' => 'emailPlaceholder',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'emailRequired',
                    ]),
                    new Email([
                        'message' => 'emailInvalid',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}







