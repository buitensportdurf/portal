<?php

namespace App\Form\Event;

use App\Entity\Event\RecurringEvent;
use App\Form\SwitchType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RecurringEventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startDate', null, [
                'label' => 'First occurrence starts',
                'help' => 'Also sets the time of every occurrence',
            ])
            ->add('endDate', null, [
                'label' => 'First occurrence ends',
                'help' => 'Sets the duration of every occurrence',
            ])
            ->add('rrule', RecurrenceRuleType::class, [
                'label' => false,
            ])
            ->add('subscriptionOpenOffset', OffsetType::class, [
                'label' => 'Subscriptions open',
                'required' => false,
                'help' => 'Leave empty to open subscriptions right away',
            ])
            ->add('subscriptionDeadlineOffset', OffsetType::class, [
                'label' => 'Subscription deadline',
                'required' => false,
                'help' => 'Leave empty to allow subscribing until the start',
            ])
            ->add('published', SwitchType::class, [
                'required' => false,
            ])
        ;

        if ($options['apply_from_choices'] !== []) {
            $builder->add('applyFrom', ChoiceType::class, [
                'label' => 'Apply changes to',
                'mapped' => false,
                'choices' => $options['apply_from_choices'],
                'data' => $options['apply_from'],
                'placeholder' => 'All upcoming events',
                'required' => false,
                'help' => 'Picking a date splits the series: earlier events keep the current settings',
            ]);
        }
        if ($options['notify_option']) {
            $builder->add('notifySubscribers', SwitchType::class, [
                'label' => 'Email subscribers about moved or cancelled events',
                'mapped' => false,
                'required' => false,
                'data' => true,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RecurringEvent::class,
            'apply_from_choices' => [],
            'apply_from' => null,
            'notify_option' => false,
        ]);
        $resolver->setAllowedTypes('apply_from_choices', 'array');
        $resolver->setAllowedTypes('apply_from', ['null', 'string']);
        $resolver->setAllowedTypes('notify_option', 'bool');
    }

    public function getParent(): string
    {
        return BaseEventType::class;
    }
}
