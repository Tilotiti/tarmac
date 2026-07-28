<?php

namespace App\Form;

use App\Entity\Anomaly;
use App\Entity\Task;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Attach an existing task — of the same equipment — to an anomaly.
 */
class AnomalyLinkTaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Anomaly $anomaly */
        $anomaly = $options['anomaly'];

        $alreadyLinkedIds = $anomaly->getTasks()
            ->map(static fn (Task $task) => $task->getId())
            ->toArray();

        $builder->add('task', EntityType::class, [
            'class' => Task::class,
            'choice_label' => 'title',
            'label' => 'anomalyLinkTask',
            'placeholder' => 'anomalyLinkTaskPlaceholder',
            'required' => true,
            'constraints' => [new Assert\NotNull(message: 'anomalyTaskRequired')],
            'query_builder' => function (EntityRepository $er) use ($anomaly, $alreadyLinkedIds) {
                $qb = $er->createQueryBuilder('task')
                    ->where('task.equipment = :equipment')
                    ->andWhere('task.status != :cancelledStatus')
                    ->setParameter('equipment', $anomaly->getEquipment())
                    ->setParameter('cancelledStatus', 'cancelled')
                    ->orderBy('task.createdAt', 'DESC');

                if ($alreadyLinkedIds !== []) {
                    $qb->andWhere('task.id NOT IN (:alreadyLinkedIds)')
                        ->setParameter('alreadyLinkedIds', $alreadyLinkedIds);
                }

                return $qb;
            },
            'attr' => ['class' => 'form-select'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);

        $resolver->setRequired('anomaly');
        $resolver->setAllowedTypes('anomaly', Anomaly::class);
    }
}
