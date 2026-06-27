<?php

namespace App\Tests\Twig\Extension;

use App\Twig\Extension\DateFormatExtension;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class DateFormatExtensionTest extends TestCase
{
    private DateFormatExtension $ext;

    protected function setUp(): void
    {
        $this->ext = new DateFormatExtension();
    }

    public function testRegistersExpectedFilters(): void
    {
        $names = array_map(fn ($f) => $f->getName(), $this->ext->getFilters());

        self::assertSame(['time_duration', 'fdate_time', 'fdate'], $names);
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

    public function testFormatDateTime(): void
    {
        $dt = new DateTimeImmutable('2026-03-14 09:05:00');

        self::assertSame('Sat 14 Mar 2026, 09:05', $this->ext->formatDateTime($dt));
    }

    public function testFormatDate(): void
    {
        $dt = new DateTimeImmutable('2026-03-14 09:05:00');

        self::assertSame('Sat 14 Mar 2026', $this->ext->formatDate($dt));
    }
}
