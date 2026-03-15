<?php

namespace App\Form\Type;

use App\Entity\Specialisation;
use App\Repository\SpecialisationRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\Options;

class SpecialisationTagType extends AbstractType
{
    public function __construct(
        private readonly SpecialisationRepository $specialisationRepository,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Specialisation::class,
            'choice_label' => 'name',
            'required' => false,
            'multiple' => true,
            'attr' => ['class' => 'd-none'],
            'choices' => fn (Options $options) => $options['club'] !== null
                ? $this->specialisationRepository->findByClub($options['club'])
                : [],
        ]);
        $resolver->setRequired('club');
        $resolver->setAllowedTypes('club', ['null', \App\Entity\Club::class]);
    }

    public function getParent(): string
    {
        return EntityType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'specialisation_tag';
    }
}
