<?php

namespace App\Recurrence;

use DateTimeImmutable;

/**
 * Pairs existing occurrences with the slots of a (possibly changed) rule.
 *
 * Each item is paired with the nearest free slot, as long as it is closer than half the shortest
 * gap between slots. That way "10:00 → 11:00" or "first Saturday → first Sunday" moves each
 * occurrence (keeping its subscriptions) instead of deleting it and creating a new one.
 */
final class OccurrenceMatcher
{
    private const int DEFAULT_TOLERANCE = 14 * 86400;

    /**
     * @param array<array-key, DateTimeImmutable> $items keyed by caller-chosen ids
     * @param list<DateTimeImmutable>             $slots
     * @return array{pairs: array<array-key, int>, unmatchedItems: list<array-key>, unmatchedSlots: list<int>}
     *         pairs maps item key => slot index
     */
    public static function match(array $items, array $slots): array
    {
        $tolerance = self::tolerance($slots);

        $candidates = [];
        foreach ($items as $itemKey => $item) {
            foreach ($slots as $slotIndex => $slot) {
                $distance = abs($slot->getTimestamp() - $item->getTimestamp());
                if ($distance <= $tolerance) {
                    $candidates[] = [$distance, $item->getTimestamp(), $itemKey, $slotIndex];
                }
            }
        }
        usort($candidates, fn(array $a, array $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        $pairs = [];
        $usedSlots = [];
        foreach ($candidates as [, , $itemKey, $slotIndex]) {
            if (isset($pairs[$itemKey]) || isset($usedSlots[$slotIndex])) {
                continue;
            }
            $pairs[$itemKey] = $slotIndex;
            $usedSlots[$slotIndex] = true;
        }

        return [
            'pairs' => $pairs,
            'unmatchedItems' => array_values(array_filter(array_keys($items), fn($key) => !isset($pairs[$key]))),
            'unmatchedSlots' => array_values(array_filter(array_keys($slots), fn(int $index) => !isset($usedSlots[$index]))),
        ];
    }

    /**
     * @param list<DateTimeImmutable> $slots
     */
    private static function tolerance(array $slots): int
    {
        $smallestGap = null;
        for ($i = 1, $count = count($slots); $i < $count; $i++) {
            $gap = $slots[$i]->getTimestamp() - $slots[$i - 1]->getTimestamp();
            $smallestGap = $smallestGap === null ? $gap : min($smallestGap, $gap);
        }

        // An hour of slack, since a gap that crosses a DST switch is an hour short
        return $smallestGap === null ? self::DEFAULT_TOLERANCE : intdiv($smallestGap, 2) + 3600;
    }
}
