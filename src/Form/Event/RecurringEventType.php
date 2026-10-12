<?php

namespace App\Form\Event;

use App\Entity\Event\RecurringEvent;
use App\Form\SwitchType;
use App\Service\LocalDate;
use DateTimeImmutable;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

class RecurringEventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startDate', null, [
                'label' => t('First occurrence starts'),
                'help' => t('Also sets the time of every occurrence'),
            ])
            ->add('endDate', null, [
                'label' => t('First occurrence ends'),
                'help' => t('Sets the duration of every occurrence'),
            ])
            ->add('rrule', RecurrenceRuleType::class, [
                'label' => false,
            ])
            ->add('subscriptionOpenOffset', OffsetType::class, [
                'label' => t('Subscriptions open'),
                'required' => false,
                'help' => t('Leave empty to open subscriptions right away'),
            ])
            ->add('subscriptionDeadlineOffset', OffsetType::class, [
                'label' => t('Subscription deadline'),
                'required' => false,
                'help' => t('Leave empty to allow subscribing until the start'),
            ])
            ->add('published', SwitchType::class, [
                'label' => t('Published'),
                'required' => false,
            ])
        ;

        if ($options['apply_from_choices'] !== []) {
            $builder->add('applyFrom', ChoiceType::class, [
                'label' => t('Apply changes to'),
                'mapped' => false,
                'choices' => $options['apply_from_choices'],
                'choice_label' => fn(string $date) => t('From {date} onwards', [
                    'date' => LocalDate::format(DateTimeImmutable::createFromFormat('!Y-m-d', $date), LocalDate::DATE),
                ]),
                'data' => $options['apply_from'],
                'placeholder' => t('All upcoming events'),
                'required' => false,
                'help' => t('Picking a date splits the series: earlier events keep the current settings'),
            ]);
        }
        if ($options['notify_option']) {
            $builder->add('notifySubscribers', SwitchType::class, [
                'label' => t('Email subscribers about moved or cancelled events'),
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
