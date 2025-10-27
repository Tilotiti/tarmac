<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class InvitationImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'label' => 'selectGivavFile',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'fileRequired',
                    ]),
                    new Assert\File([
                        'maxSize' => '10M',
                        'mimeTypes' => [
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/octet-stream',
                        ],
                        'mimeTypesMessage' => 'invalidFileFormat',
                    ]),
                ],
                'attr' => [
                    'accept' => '.xls,.xlsx',
                ],
                'help' => 'fileFormatHelp',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
        ]);
    }
}

