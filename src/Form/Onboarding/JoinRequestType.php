<?php

namespace App\Form\Onboarding;

use App\Entity\JoinRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

class JoinRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('message', TextareaType::class, [
            'label' => 'joinRequestMessage',
            'help' => 'joinRequestMessageHelp',
            'required' => false,
            'attr' => ['rows' => 4, 'maxlength' => 500],
            'constraints' => [new Length(max: 500, maxMessage: 'valueTooLong')],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => JoinRequest::class]);
    }
}
