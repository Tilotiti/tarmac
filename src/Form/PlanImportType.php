<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotNull;

class PlanImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('file', FileType::class, [
            'label' => 'planImportFile',
            'mapped' => false,
            'required' => true,
            'attr' => [
                'accept' => '.xlsx',
                'class' => 'form-control',
            ],
            'constraints' => [
                new NotNull([
                    'message' => 'planImportFileRequired',
                ]),
                new File([
                    'maxSize' => '4M',
                    'mimeTypes' => [
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/octet-stream',
                    ],
                    'mimeTypesMessage' => 'planImportInvalidMime',
                ]),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}


