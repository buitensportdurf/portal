<?php

namespace App\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class DateFormatExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('time_duration', [$this, 'formatTimeDuration'], ['is_safe' => ['html']]),
        ];
    }

    public function formatTimeDuration(\DateInterval $interval): string
    {
        $format = '';
        if ($interval->d > 0) {
            $format .= ' %d day';
            if ($interval->d > 1) {
                $format .= 's';
            }
        }
        if ($interval->h > 0) {
            $format .= ' %h hour';
            if ($interval->h > 1) {
                $format .= 's';
            }
        }
        if ($interval->i > 0) {
            $format .= ' %i minute';
            if ($interval->i > 1) {
                $format .= 's';
            }
        }
        if ($interval->s > 0) {
            $format .= ' %s second';
            if ($interval->s > 1) {
                $format .= 's';
            }
        }
        return $interval->format(trim($format));
    }
}
