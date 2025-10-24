<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstname', TextType::class, [
                'label' => 'firstname',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'placeholder' => 'firstnamePlaceholder',
                ],
            ])
            ->add('lastname', TextType::class, [
                'label' => 'lastname',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'placeholder' => 'lastnamePlaceholder',
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'options' => [
                    'attr' => [
                        'autocomplete' => 'new-password',
                    ],
                ],
                'first_options' => [
                    'constraints' => [
                        new NotBlank([
                            'message' => 'passwordRequired',
                        ]),
                        new Length([
                            'min' => 6,
                            'minMessage' => 'passwordMinLength',
                            'max' => 4096,
                        ]),
                    ],
                    'label' => 'password',
                ],
                'second_options' => [
                    'label' => 'confirmPassword',
                ],
                'invalid_message' => 'passwordMatch',
                'mapped' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}





