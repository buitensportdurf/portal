<?php

namespace App\Tests\Service;

use App\Service\LocalDate;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class LocalDateTest extends TestCase
{
    public function testEnglish(): void
    {
        $date = new DateTimeImmutable('2026-03-14 09:05:00');

        self::assertSame('Sat 14 Mar 2026, 09:05', LocalDate::format($date, LocalDate::DATE_TIME, 'en'));
        self::assertSame('Sat 14 Mar 2026', LocalDate::format($date, LocalDate::DATE, 'en'));
        self::assertSame('14 Mar', LocalDate::format($date, LocalDate::DAY_MONTH, 'en'));
    }

    public function testDutch(): void
    {
        $date = new DateTimeImmutable('2026-03-14 09:05:00');

        self::assertSame('za 14 mrt 2026, 09:05', LocalDate::format($date, LocalDate::DATE_TIME, 'nl'));
        self::assertSame('14 mrt 2026', LocalDate::format($date, LocalDate::SHORT_DATE, 'nl'));
    }

    public function testFollowsTheDefaultLocale(): void
    {
        $previous = \Locale::getDefault();
        \Locale::setDefault('nl');
        try {
            self::assertSame('za 14 mrt 2026', LocalDate::format(new DateTimeImmutable('2026-03-14'), LocalDate::DATE));
        } finally {
            \Locale::setDefault($previous);
        }
    }

    public function testKeepsTheDateTimezone(): void
    {
        $date = new DateTimeImmutable('2026-03-14 23:30:00', new \DateTimeZone('Europe/Amsterdam'));

        self::assertSame('Sat 14 Mar 2026, 23:30', LocalDate::format($date, LocalDate::DATE_TIME, 'en'));
    }
}
