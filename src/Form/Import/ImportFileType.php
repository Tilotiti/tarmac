<?php

namespace App\Form\Import;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotNull;

class ImportFileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('file', FileType::class, [
            'label' => 'importFile',
            'help' => 'importFileHelp',
            'attr' => ['accept' => '.csv,.xls,.xlsx'],
            'constraints' => [
                new NotNull(message: 'valueRequired'),
                new File(
                    maxSize: '2M',
                    maxSizeMessage: 'fileTooLarge',
                    // Un CSV exporté par un tableur (Excel, LibreOffice…) est souvent détecté
                    // comme text/plain par le sniffing MIME du serveur, pas text/csv : on accepte
                    // explicitement les deux pour ne pas rejeter des fichiers valides.
                    extensions: [
                        'csv' => ['text/csv', 'text/plain', 'application/csv', 'text/x-comma-separated-values', 'text/x-csv'],
                        'xls',
                        'xlsx',
                    ],
                    extensionsMessage: 'importUnsupportedFormat',
                    mimeTypesMessage: 'importUnsupportedFormat',
                ),
            ],
        ]);
    }
}
