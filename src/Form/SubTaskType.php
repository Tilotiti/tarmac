<?php

namespace App\Form;

use App\Entity\SubTask;
use App\Form\Type\SpecialisationTagType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

class SubTaskType extends AbstractType
{
    /**
     * Valeurs du champ `insertPosition` autres qu'un identifiant de sous-tâche.
     */
    public const INSERT_AT_END = 'end';
    public const INSERT_AT_START = 'start';

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'title',
                'required' => true,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'description',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3],
            ])
            ->add('difficulty', ChoiceType::class, [
                'label' => 'difficulty',
                'choices' => [
                    'debutant' => 1,
                    'experimente' => 2,
                    'expert' => 3,
                ],
                'required' => true,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('documentation', FileType::class, [
                'label' => 'subTaskDocumentation',
                'required' => false,
                'mapped' => false,
                'upload' => 'documentation',
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/*,.pdf,application/pdf',
                ],
                'constraints' => [
                    new Assert\File(
                        maxSize: '10M',
                        mimeTypes: [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'image/webp',
                            'application/pdf',
                        ],
                        mimeTypesMessage: 'invalidFileFormat',
                    ),
                ],
            ])
            ->add('requiresInspection', CheckboxType::class, [
                'label' => 'requiresInspection',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
        ;

        // Emplacement de la nouvelle sous-tâche dans la tâche. Uniquement à la création :
        // à l'édition, l'option reste vide et le champ n'est pas rendu (l'ordre se change
        // alors via le mode « Réorganiser » de la page de la tâche).
        if ($options['position_choices'] !== []) {
            // `choices` est une liste plate et non un tableau libellé => valeur : deux
            // sous-tâches peuvent porter le même titre, et des libellés identiques
            // s'écraseraient entre eux. Le rang affiché lève d'ailleurs l'ambiguïté
            // pour l'utilisateur.
            $values = [self::INSERT_AT_END, self::INSERT_AT_START];
            $labels = [
                self::INSERT_AT_END => $this->translator->trans('insertAtEnd'),
                self::INSERT_AT_START => $this->translator->trans('insertAtStart'),
            ];

            $rank = 0;
            foreach ($options['position_choices'] as $existing) {
                if ($existing->getId() === null) {
                    continue;
                }

                ++$rank;
                $value = (string) $existing->getId();
                $values[] = $value;
                $labels[$value] = $this->translator->trans('insertAfterSubTask', [
                    'rank' => (string) $rank,
                    'title' => $existing->getTitle(),
                ]);
            }

            $builder->add('insertPosition', ChoiceType::class, [
                'label' => 'insertPosition',
                'help' => 'insertPositionHelp',
                'mapped' => false,
                'required' => true,
                'choices' => $values,
                'choice_label' => static fn (string $value) => $labels[$value] ?? $value,
                'choice_translation_domain' => false,
                'data' => self::INSERT_AT_END,
                'attr' => ['class' => 'form-select'],
            ]);
        }

        if ($options['can_manage_specialisations'] ?? false) {
            $builder->add('specialisations', SpecialisationTagType::class, [
                'label' => 'specialisations',
                'club' => $options['club'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SubTask::class,
            'club' => null,
            'can_manage_specialisations' => false,
            'position_choices' => [],
        ]);
        $resolver->setAllowedTypes('club', ['null', \App\Entity\Club::class]);
        $resolver->setAllowedTypes('can_manage_specialisations', 'bool');
        $resolver->setAllowedTypes('position_choices', SubTask::class . '[]');
    }
}

