<?php

namespace App\Form\Type;

use App\Form\DataTransformer\AerodromeToIdTransformer;
use App\Repository\AerodromeRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Champ caché portant l'id d'un aérodrome ; l'autocomplétion est assurée par
 * le contrôleur Stimulus `aerodrome-search` via le partial component/aerodromeSelector.
 */
final class AerodromeSelectorType extends AbstractType
{
    public function __construct(private readonly AerodromeRepository $aerodromeRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new AerodromeToIdTransformer($this->aerodromeRepository));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'required' => false,
            'invalid_message' => 'aerodromeUnknown',
        ]);
    }

    public function getParent(): string
    {
        return HiddenType::class;
    }
}
