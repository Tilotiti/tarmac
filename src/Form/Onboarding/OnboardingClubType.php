<?php

namespace App\Form\Onboarding;

use App\Entity\Club;
use App\Form\Type\AerodromeSelectorType;
use App\Service\SubdomainService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class OnboardingClubType extends AbstractType
{
    public function __construct(private readonly SubdomainService $subdomainService)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'clubName',
                'attr' => ['placeholder' => 'clubNamePlaceholder', 'autocomplete' => 'organization'],
                'constraints' => [new NotBlank(message: 'clubNameRequired'), new Length(max: 255, maxMessage: 'valueTooLong')],
            ])
            ->add('subdomain', TextType::class, [
                'label' => 'clubAddress',
                'attr' => ['autocomplete' => 'off', 'autocapitalize' => 'none', 'spellcheck' => 'false'],
                'constraints' => [
                    new NotBlank(message: 'subdomainRequired'),
                    new Regex(pattern: SubdomainService::PATTERN, message: 'subdomainInvalid'),
                    new Callback(function (?string $value, ExecutionContextInterface $context): void {
                        if ($value !== null && $this->subdomainService->isReserved($value)) {
                            $context->buildViolation('subdomainReserved')->addViolation();
                        }
                    }),
                ],
            ])
            ->add('aerodrome', AerodromeSelectorType::class, [
                'constraints' => [new NotNull(message: 'aerodromeRequired')],
            ])
            ->add('confirmed', HiddenType::class, ['mapped' => false, 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Club::class,
        ]);
    }
}
