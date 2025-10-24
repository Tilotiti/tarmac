<?php

namespace App\Form;

use App\Entity\Membership;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class MembershipType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('isManager', CheckboxType::class, [
                'label' => 'Manager',
                'required' => false,
                'help' => 'Peut gérer le club',
            ])
            ->add('isInspector', CheckboxType::class, [
                'label' => 'Inspecteur',
                'required' => false,
                'help' => 'Peut inspecter et valider les activités sensibles du club',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Membership::class,
        ]);
    }
}


