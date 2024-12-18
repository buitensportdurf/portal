<?php

namespace App\Form\Event;

use App\Entity\Event\RecurringEvent;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RecurringEventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('recurrenceRule', TextType::class, [
            'priority' => 1,
            'label' => 'Recurrence rule, relative to start date (e.g. "P1D" for daily, +1 month, next week). Can be multiple seperated by ";"',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RecurringEvent::class,
        ]);
    }

    public function getParent(): string
    {
        return BaseEventType::class;
    }
}
