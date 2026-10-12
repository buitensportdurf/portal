<?php

namespace App\Form\Event;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;

use function Symfony\Component\Translation\t;

/**
 * An amount of time before an occurrence starts, stored as minutes (null when left empty).
 */
class OffsetType extends AbstractType
{
    private const array UNITS = ['hours' => 60, 'days' => 1440, 'weeks' => 10080];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amount', IntegerType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['min' => 0, 'placeholder' => '—'],
            ])
            ->add('unit', ChoiceType::class, [
                'label' => false,
                'choices' => array_keys(self::UNITS),
                'choice_label' => fn(string $unit) => match ($unit) {
                    'hours' => t('hours before the start'),
                    'days' => t('days before the start'),
                    'weeks' => t('weeks before the start'),
                },
            ])
        ;

        $builder->addModelTransformer(new CallbackTransformer(
            self::toAmount(...),
            self::toMinutes(...),
        ));
    }

    /**
     * @return array{amount: ?int, unit: string}
     */
    public static function toAmount(?int $minutes): array
    {
        if ($minutes === null) {
            return ['amount' => null, 'unit' => 'days'];
        }
        foreach (array_reverse(self::UNITS) as $unit => $size) {
            if ($minutes % $size === 0) {
                return ['amount' => intdiv($minutes, $size), 'unit' => $unit];
            }
        }

        return ['amount' => intdiv($minutes, 60), 'unit' => 'hours'];
    }

    public static function toMinutes(?array $value): ?int
    {
        if ($value === null || $value['amount'] === null) {
            return null;
        }

        return $value['amount'] * (self::UNITS[$value['unit']] ?? 60);
    }
}
