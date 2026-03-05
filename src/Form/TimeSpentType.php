<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Compound form type: hours + minutes.
 * Data is the decimal timeSpent (hours), e.g. 1.5 = 1h30.
 * Renders as two integer inputs (hours, minutes) and converts automatically.
 */
class TimeSpentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('hours', IntegerType::class, [
                'label' => $options['hours_label'],
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'max' => 999,
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'timeSpentRequired'),
                    new Assert\GreaterThanOrEqual(0, message: 'timeSpentMustBePositive'),
                    new Assert\LessThanOrEqual(999, message: 'timeSpentTooHigh'),
                ],
            ])
            ->add('minutes', IntegerType::class, [
                'label' => $options['minutes_label'],
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'max' => 59,
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'timeSpentRequired'),
                    new Assert\Range(min: 0, max: 59, notInRangeMessage: 'minutesMustBeBetween0And59'),
                ],
            ])
        ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options): void {
            $data = $event->getData();
            if (is_numeric($data)) {
                $decimal = (float) $data;
                $hours = (int) floor($decimal);
                $minutes = (int) round(($decimal - $hours) * 60);
                if ($minutes >= 60) {
                    $hours += 1;
                    $minutes = 0;
                }
                $event->setData(['hours' => $hours, 'minutes' => $minutes]);
            } else {
                $event->setData(['hours' => $options['default_hours'], 'minutes' => $options['default_minutes']]);
            }
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (is_array($data) && isset($data['hours'], $data['minutes'])) {
                $hours = (int) ($data['hours'] ?? 0);
                $minutes = (int) ($data['minutes'] ?? 0);
                $minutes = max(0, min(59, $minutes));
                $decimal = round($hours + $minutes / 60, 2);
                $event->setData((string) $decimal);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'hours_label' => 'hours',
            'minutes_label' => 'minutes',
            'default_hours' => 1,
            'default_minutes' => 0,
            'compound' => true,
            'invalid_message' => 'timeSpentInvalid',
        ]);
        $resolver->setAllowedTypes('hours_label', ['string', 'bool']);
        $resolver->setAllowedTypes('minutes_label', ['string', 'bool']);
    }

    public function getBlockPrefix(): string
    {
        return 'time_spent_type';
    }
}
