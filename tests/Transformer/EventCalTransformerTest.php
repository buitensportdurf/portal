<?php

namespace App\Tests\Transformer;

use App\Entity\Event\Event;
use App\Transformer\EventCalTransformer;
use DateTimeImmutable;
use Eluceo\iCal\Domain\ValueObject\MultiDay;
use Eluceo\iCal\Domain\ValueObject\TimeSpan;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EventCalTransformerTest extends TestCase
{
    private function createEvent(DateTimeImmutable $start, DateTimeImmutable $end): Event
    {
        $event = new Event();
        $event->name = 'Bouldering';
        $event->description = 'Indoor session';
        $event->location = 'Gym A';
        $event->startDate = $start;
        $event->endDate = $end;

        return $event;
    }

    private function transformer(): EventCalTransformer
    {
        $url = $this->createMock(UrlGeneratorInterface::class);
        $url->method('generate')->willReturn('https://example.test/event/42');

        return new EventCalTransformer($url);
    }

    public function testShortEventUsesTimeSpan(): void
    {
        $event = $this->createEvent(
            new DateTimeImmutable('2026-03-14 18:00'),
            new DateTimeImmutable('2026-03-14 20:00'),
        );

        $ical = $this->transformer()->transform($event);

        self::assertInstanceOf(TimeSpan::class, $ical->getOccurrence());
    }

    public function testMultiDayEventUsesMultiDay(): void
    {
        $event = $this->createEvent(
            new DateTimeImmutable('2026-03-14 18:00'),
            new DateTimeImmutable('2026-03-17 12:00'),
        );

        $ical = $this->transformer()->transform($event);

        self::assertInstanceOf(MultiDay::class, $ical->getOccurrence());
    }

    public function testBoundaryAtTwoDaysStillUsesTimeSpan(): void
    {
        // diff->d == 1 — getDuration < 2 days threshold, so TimeSpan path
        $event = $this->createEvent(
            new DateTimeImmutable('2026-03-14 18:00'),
            new DateTimeImmutable('2026-03-15 20:00'),
        );

        $ical = $this->transformer()->transform($event);

        self::assertInstanceOf(TimeSpan::class, $ical->getOccurrence());
    }

    public function testPopulatesSummaryDescriptionLocationAndUrl(): void
    {
        $event = $this->createEvent(
            new DateTimeImmutable('2026-03-14 18:00'),
            new DateTimeImmutable('2026-03-14 20:00'),
        );

        $ical = $this->transformer()->transform($event);

        self::assertSame('Bouldering', $ical->getSummary());
        self::assertSame('Indoor session', $ical->getDescription());
        self::assertSame('Gym A', (string) $ical->getLocation());
        self::assertSame('https://example.test/event/42', $ical->getUrl()->getUri());
    }
}
