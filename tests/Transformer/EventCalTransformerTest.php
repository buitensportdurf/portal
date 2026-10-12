<?php

namespace App\Tests\Transformer;

use App\Entity\Event\Event;
use App\Transformer\EventCalTransformer;
use DateTimeImmutable;
use Eluceo\iCal\Domain\Enum\EventStatus;
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
        $url = $this->createStub(UrlGeneratorInterface::class);
        $url->method('generate')->willReturn('https://example.test/event/42');

        return new EventCalTransformer($url);
    }

    public function testEventsUseTimesInTheirTimezone(): void
    {
        $event = $this->createEvent(
            new DateTimeImmutable('2026-03-14 18:00'),
            new DateTimeImmutable('2026-03-14 20:00'),
        );

        $occurrence = $this->transformer()->transform($event)->getOccurrence();

        self::assertInstanceOf(TimeSpan::class, $occurrence);
        self::assertTrue($occurrence->getBegin()->hasDateTimeZone());
        self::assertSame('2026-03-14 18:00', $occurrence->getBegin()->getDateTime()->format('Y-m-d H:i'));
    }

    public function testMultiDayEventKeepsItsTimes(): void
    {
        // A weekend away: Friday evening to Sunday evening, not an all-day event
        $event = $this->createEvent(
            new DateTimeImmutable('2026-01-16 18:00'),
            new DateTimeImmutable('2026-01-18 18:00'),
        );

        $occurrence = $this->transformer()->transform($event)->getOccurrence();

        self::assertInstanceOf(TimeSpan::class, $occurrence);
        self::assertSame('2026-01-18 18:00', $occurrence->getEnd()->getDateTime()->format('Y-m-d H:i'));
    }

    public function testPopulatesSummaryDescriptionLocationAndUrl(): void
    {
        $event = $this->createEvent(
            new DateTimeImmutable('2026-03-14 18:00'),
            new DateTimeImmutable('2026-03-14 20:00'),
        );

        $ical = $this->transformer()->transform($event);

        self::assertSame('Bouldering', $ical->getSummary());
        self::assertSame("Indoor session\n\nhttps://example.test/event/42", $ical->getDescription());
        self::assertSame('Gym A', (string) $ical->getLocation());
        self::assertSame('https://example.test/event/42', $ical->getUrl()->getUri());
    }

    public function testDescriptionIsPlainTextWithLinkTargets(): void
    {
        $event = $this->createEvent(
            new DateTimeImmutable('2026-03-14 18:00'),
            new DateTimeImmutable('2026-03-14 20:00'),
        );
        $event->description = "**Bring** your own gear & snacks.\n\n- Shoes\n- Chalk\n\nSign up [here](https://example.test/form) or at https://example.test/x";

        $description = $this->transformer()->transform($event)->getDescription();

        self::assertSame(
            "Bring your own gear & snacks.\n\n• Shoes\n• Chalk\n\nSign up here (https://example.test/form) or at https://example.test/x\n\nhttps://example.test/event/42",
            $description,
        );
    }

    public function testCancelledEventIsMarkedInTitleAndStatus(): void
    {
        $event = $this->createEvent(
            new DateTimeImmutable('2026-03-14 18:00'),
            new DateTimeImmutable('2026-03-14 20:00'),
        );
        $event->cancelledAt = new DateTimeImmutable();

        $ical = $this->transformer()->transform($event);

        self::assertSame('Cancelled: Bouldering', $ical->getSummary());
        self::assertSame(EventStatus::CANCELLED(), $ical->getStatus());
    }
}
