<?php

namespace App\Form;

use App\Entity\Club;
use App\Form\Type\AerodromeSelectorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * Réglage de l'aérodrome par un gestionnaire : le terrain vient de la liste,
 * un terrain absent se signale à l'administrateur (seul l'admin saisit une commune libre).
 */
class ClubAerodromeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('aerodrome', AerodromeSelectorType::class, [
            'constraints' => [new NotNull(message: 'aerodromeRequired')],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Club::class]);
    }
}
