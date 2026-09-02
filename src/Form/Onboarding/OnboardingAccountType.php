<?php

namespace App\Form\Onboarding;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;

class OnboardingAccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstname', TextType::class, [
                'label' => 'firstname',
                'attr' => ['placeholder' => 'firstnamePlaceholder', 'autocomplete' => 'given-name'],
                'constraints' => [new NotBlank(message: 'firstnameRequired')],
            ])
            ->add('lastname', TextType::class, [
                'label' => 'lastname',
                'attr' => ['placeholder' => 'lastnamePlaceholder', 'autocomplete' => 'family-name'],
                'constraints' => [new NotBlank(message: 'lastnameRequired')],
            ])
            ->add('email', EmailType::class, [
                'label' => 'email',
                'attr' => ['placeholder' => 'emailPlaceholder', 'autocomplete' => 'email'],
                'constraints' => [new NotBlank(message: 'emailRequired'), new Email(message: 'emailInvalid')],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'options' => ['attr' => ['autocomplete' => 'new-password']],
                'first_options' => [
                    'label' => 'password',
                    'constraints' => [
                        new NotBlank(message: 'passwordRequired'),
                        new Length(min: 6, minMessage: 'passwordMinLength', max: 4096),
                        new PasswordStrength(minScore: PasswordStrength::STRENGTH_WEAK, message: 'passwordTooWeak'),
                        new NotCompromisedPassword(message: 'passwordCompromised'),
                    ],
                ],
                'second_options' => ['label' => 'confirmPassword'],
                'invalid_message' => 'passwordMatch',
            ])
            // Honeypot : invisible pour un humain, rempli par les robots.
            ->add('website', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => false,
                'attr' => ['tabindex' => '-1', 'autocomplete' => 'off', 'aria-hidden' => 'true'],
                'row_attr' => ['class' => 'onboarding-honeypot'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
