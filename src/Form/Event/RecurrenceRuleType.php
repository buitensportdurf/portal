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
                'label' => 'Repeats',
                'choices' => ['Daily' => 'DAILY', 'Weekly' => 'WEEKLY', 'Monthly' => 'MONTHLY', 'Yearly' => 'YEARLY'],
            ])
            ->add('interval', IntegerType::class, [
                'label' => 'Every',
                'attr' => ['min' => 1],
                'help' => 'e.g. 2 for every other week',
            ])
            ->add('weekdays', ChoiceType::class, [
                'label' => 'On',
                'choices' => self::WEEKDAYS,
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'help' => 'None selected: the weekday of the first occurrence',
            ])
            ->add('monthlyMode', ChoiceType::class, [
                'label' => 'On',
                'choices' => ['The same day of the month as the first occurrence' => 'day', 'A weekday of the month' => 'weekday'],
                'expanded' => true,
            ])
            ->add('ordinal', ChoiceType::class, [
                'label' => false,
                'choices' => ['First' => 1, 'Second' => 2, 'Third' => 3, 'Fourth' => 4, 'Last' => -1],
            ])
            ->add('weekday', ChoiceType::class, [
                'label' => false,
                'choices' => self::WEEKDAYS,
            ])
            ->add('end', ChoiceType::class, [
                'label' => 'Ends',
                'choices' => ['Never' => 'never', 'On a date' => 'until', 'After a number of occurrences' => 'count'],
                'expanded' => true,
            ])
            ->add('until', DateType::class, [
                'label' => 'Last date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('count', IntegerType::class, [
                'label' => 'Number of occurrences',
                'attr' => ['min' => 1],
                'required' => false,
            ])
        ;

        $builder->addModelTransformer(new CallbackTransformer(
            self::toChoices(...),
            self::toRule(...),
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'invalid_message' => 'Please complete the recurrence: {{ error }}',
            'invalid_message_parameters' => ['{{ error }}' => 'check the end date or number of occurrences'],
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
