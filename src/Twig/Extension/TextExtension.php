<?php

namespace App\Twig\Extension;

use App\Twig\Runtime\TextExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class TextExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            // If your filter generates SAFE HTML, you should add a third
            // parameter: ['is_safe' => ['html']]
            // Reference: https://twig.symfony.com/doc/3.x/advanced.html#automatic-escaping
            new TwigFilter('truncate', [$this, 'truncate']),
        ];
    }

    public function truncate(?string $text, int $length = 30, string $separator = '...'): ?string
    {
        if (strlen($text) > $length) {
            $text = substr($text, 0, $length);
            return substr($text, 0, strrpos($text, ' ')) . ' ' . $separator;
        } else {
            return $text;
        }
    }
}
