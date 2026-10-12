<?php

namespace App\Tests\Twig\Extension;

use App\Twig\Extension\DateFormatExtension;
use DateInterval;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

class DateFormatExtensionTest extends TestCase
{
    private DateFormatExtension $ext;

    protected function setUp(): void
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $messages = [];
        foreach (['day', 'hour', 'minute', 'second'] as $unit) {
            $key = sprintf('{count, plural, one {# %s} other {# %ss}}', $unit, $unit);
            $messages[$key] = $key;
        }
        $translator->addResource('array', $messages, 'en', 'messages+intl-icu');
        $this->ext = new DateFormatExtension($translator);
    }

    public function testRegistersExpectedFilters(): void
    {
        $names = array_map(fn ($f) => $f->getName(), $this->ext->getFilters());

        self::assertSame(['time_duration', 'fdate_time', 'fdate', 'fdate_short', 'fday_month'], $names);
    }

    public function testTimeDurationSingularAndPluralUnits(): void
    {
        $singular = new DateInterval('P1DT1H1M1S');
        $plural = new DateInterval('P2DT3H4M5S');

        self::assertSame('1 day 1 hour 1 minute 1 second', $this->ext->formatTimeDuration($singular));
        self::assertSame('2 days 3 hours 4 minutes 5 seconds', $this->ext->formatTimeDuration($plural));
    }

    public function testTimeDurationOmitsZeroComponents(): void
    {
        $twoHours = new DateInterval('PT2H');

        self::assertSame('2 hours', $this->ext->formatTimeDuration($twoHours));
    }
}
