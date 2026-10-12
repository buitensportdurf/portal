<?php

namespace App\Form\Event;

use App\Recurrence\OccurrenceCalculator;
use DateTimeImmutable;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

use function Symfony\Component\Translation\t;

/**
 * Builds an RRULE string from a few plain choices: how often, on which days, and until when.
 */
class RecurrenceRuleType extends AbstractType
{
    public const array WEEKDAYS = [
        'Monday' => 'MO', 'Tuesday' => 'TU', 'Wednesday' => 'WE', 'Thursday' => 'TH',
        'Friday' => 'FR', 'Saturday' => 'SA', 'Sunday' => 'SU',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('frequency', ChoiceType::class, [
                'label' => t('Repeats'),
                'choices' => OccurrenceCalculator::FREQUENCIES,
                'choice_label' => fn(string $frequency) => match ($frequency) {
                    'DAILY' => t('Daily'),
                    'WEEKLY' => t('Weekly'),
                    'MONTHLY' => t('Monthly'),
                    'YEARLY' => t('Yearly'),
                },
            ])
            ->add('interval', IntegerType::class, [
                'label' => t('Every'),
                'attr' => ['min' => 1],
                'help' => t('e.g. 2 for every other week'),
            ])
            ->add('weekdays', ChoiceType::class, [
                'label' => t('On'),
                'choices' => self::WEEKDAYS,
                'choice_label' => self::weekdayLabel(...),
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'help' => t('None selected: the weekday of the first occurrence'),
            ])
            ->add('monthlyMode', ChoiceType::class, [
                'label' => t('On'),
                'choices' => ['day', 'weekday'],
                'choice_label' => fn(string $mode) => match ($mode) {
                    'day' => t('The same day of the month as the first occurrence'),
                    'weekday' => t('A weekday of the month'),
                },
                'expanded' => true,
            ])
            ->add('ordinal', ChoiceType::class, [
                'label' => false,
                'choices' => [1, 2, 3, 4, -1],
                'choice_label' => fn(int $ordinal) => match ($ordinal) {
                    1 => t('First'),
                    2 => t('Second'),
                    3 => t('Third'),
                    4 => t('Fourth'),
                    -1 => t('Last'),
                },
            ])
            ->add('weekday', ChoiceType::class, [
                'label' => false,
                'choices' => self::WEEKDAYS,
                'choice_label' => self::weekdayLabel(...),
            ])
            ->add('end', ChoiceType::class, [
                'label' => t('Ends'),
                'choices' => ['never', 'until', 'count'],
                'choice_label' => fn(string $end) => match ($end) {
                    'never' => t('Never'),
                    'until' => t('On a date'),
                    'count' => t('After a number of occurrences'),
                },
                'expanded' => true,
            ])
            ->add('until', DateType::class, [
                'label' => t('Last date'),
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('count', IntegerType::class, [
                'label' => t('Number of occurrences'),
                'attr' => ['min' => 1],
                'required' => false,
            ])
        ;

        $builder->addModelTransformer(new CallbackTransformer(
            self::toChoices(...),
            self::toRule(...),
        ));
    }

    public static function weekdayLabel(string $day): TranslatableMessage
    {
        return match ($day) {
            'MO' => t('Monday'),
            'TU' => t('Tuesday'),
            'WE' => t('Wednesday'),
            'TH' => t('Thursday'),
            'FR' => t('Friday'),
            'SA' => t('Saturday'),
            'SU' => t('Sunday'),
        };
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'invalid_message' => 'Please complete the recurrence: check the end date or number of occurrences.',
            'error_bubbling' => false,
        ]);
    }

    public static function toChoices(?string $rrule): array
    {
        $parts = OccurrenceCalculator::parse((string) $rrule);
        $choices = [
            'frequency' => $parts['FREQ'] ?? 'WEEKLY',
            'interval' => (int) ($parts['INTERVAL'] ?? 1),
            'weekdays' => [],
            'monthlyMode' => 'day',
            'ordinal' => 1,
            'weekday' => 'MO',
            'end' => 'never',
            'until' => null,
            'count' => null,
        ];

        $days = array_filter(explode(',', $parts['BYDAY'] ?? ''));
        if ($choices['frequency'] === 'MONTHLY' && count($days) === 1
            && preg_match('/^([+-]?\d)?(MO|TU|WE|TH|FR|SA|SU)$/', $days[0], $m)) {
            $choices['monthlyMode'] = 'weekday';
            $choices['ordinal'] = (int) ($m[1] ?: 1);
            $choices['weekday'] = $m[2];
        } elseif ($choices['frequency'] === 'WEEKLY') {
            $choices['weekdays'] = array_values($days);
        }

        if (isset($parts['UNTIL'])) {
            $choices['end'] = 'until';
            $choices['until'] = DateTimeImmutable::createFromFormat('!Ymd', substr($parts['UNTIL'], 0, 8)) ?: null;
        } elseif (isset($parts['COUNT'])) {
            $choices['end'] = 'count';
            $choices['count'] = (int) $parts['COUNT'];
        }

        return $choices;
    }

    public static function toRule(?array $choices): ?string
    {
        if ($choices === null) {
            return null;
        }
        $frequency = $choices['frequency'] ?? 'WEEKLY';
        $parts = [
            'FREQ' => $frequency,
            'INTERVAL' => ($choices['interval'] ?? 1) > 1 ? (string) $choices['interval'] : null,
        ];

        if ($frequency === 'WEEKLY' && !empty($choices['weekdays'])) {
            $order = array_flip(array_values(self::WEEKDAYS));
            $days = $choices['weekdays'];
            usort($days, fn(string $a, string $b) => $order[$a] <=> $order[$b]);
            $parts['BYDAY'] = implode(',', $days);
        }
        if ($frequency === 'MONTHLY' && ($choices['monthlyMode'] ?? 'day') === 'weekday') {
            $parts['BYDAY'] = ($choices['ordinal'] ?? 1) . ($choices['weekday'] ?? 'MO');
        }

        switch ($choices['end'] ?? 'never') {
            case 'until':
                if (!$choices['until'] instanceof \DateTimeInterface) {
                    throw new TransformationFailedException('Missing end date', 0, null, 'Please choose the last date of the series.');
                }
                $parts['UNTIL'] = $choices['until']->format('Ymd');
                break;
            case 'count':
                if (($choices['count'] ?? 0) < 1) {
                    throw new TransformationFailedException('Missing count', 0, null, 'Please enter the number of occurrences.');
                }
                $parts['COUNT'] = (string) $choices['count'];
                break;
        }

        return OccurrenceCalculator::compose($parts);
    }
}
