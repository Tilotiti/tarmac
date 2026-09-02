<?php

namespace App\Form;

use App\Entity\Club;
use App\Form\Type\AerodromeSelectorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ClubAerodromeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('aerodrome', AerodromeSelectorType::class)
            ->add('locationLabel', TextType::class, ['label' => 'municipality', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Club::class,
            'constraints' => [
                new Callback(static function (Club $club, ExecutionContextInterface $context): void {
                    if ($club->getAerodrome() === null && trim((string) $club->getLocationLabel()) === '') {
                        $context->buildViolation('aerodromeOrMunicipalityRequired')->atPath('locationLabel')->addViolation();
                    }
                }),
            ],
        ]);
    }
}
