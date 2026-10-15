<?php

namespace App\Tests\Transformer;

use App\Entity\Event\Event;
use App\Entity\Event\EventSubscription;
use App\Entity\User;
use App\Transformer\EventCalTransformer;
use DateTimeImmutable;
use Eluceo\iCal\Domain\Entity\Attendee;
use Eluceo\iCal\Domain\Enum\EventStatus;
use Eluceo\iCal\Domain\ValueObject\TimeSpan;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\Translation\TranslatorInterface;

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

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            fn(string $id, array $parameters = []): string => \MessageFormatter::formatMessage('en', $id, $parameters),
        );

        return new EventCalTransformer($url, $translator, 'test-secret');
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

    private function subscribe(Event $event, string $name, bool $guest = false, int $amount = 1): User
    {
        $user = new User();
        $user->name = $name;
        $user->guest = $guest;
        new \ReflectionProperty(User::class, 'id')->setValue($user, new Ulid());
        $subscription = new EventSubscription();
        $subscription->createdUser = $user;
        $subscription->amount = $amount;
        $subscription->event = $event;

        return $user;
    }

    public function testAttendeesOnlyWhenAskedFor(): void
    {
        $event = $this->createEvent(new DateTimeImmutable('2026-03-14 18:00'), new DateTimeImmutable('2026-03-14 20:00'));
        $this->subscribe($event, 'Anna');

        self::assertFalse($this->transformer()->transform($event)->hasAttendee());
        self::assertTrue($this->transformer()->transform($event, true)->hasAttendee());
    }

    public function testAttendeesShowNamesNumberedGuestsAndAnonymousAddresses(): void
    {
        $event = $this->createEvent(new DateTimeImmutable('2026-03-14 18:00'), new DateTimeImmutable('2026-03-14 20:00'));
        $anna = $this->subscribe($event, 'Anna', amount: 3);
        $this->subscribe($event, 'Secret Guest Name', guest: true);
        $this->subscribe($event, 'Bram');
        $this->subscribe($event, 'Other Guest Name', guest: true);

        $attendees = $this->transformer()->transform($event, true)->getAttendees();

        self::assertSame(
            ['Anna (+2)', 'Guest 1', 'Bram', 'Guest 2'],
            array_map(fn(Attendee $attendee) => $attendee->getDisplayName(), $attendees),
        );
        self::assertFalse($attendees[0]->isRSVPenabled());
        $addresses = array_map(fn(Attendee $attendee) => $attendee->getEmailAddress()->getEmailAddress(), $attendees);
        self::assertCount(4, array_unique($addresses));
        foreach ($addresses as $address) {
            self::assertMatchesRegularExpression('/^[0-9a-f]{16}@participant\.invalid$/', $address);
            self::assertStringNotContainsString(strtolower((string) $anna->id), $address);
        }

        // The same person gets the same address in every event
        $other = $this->createEvent(new DateTimeImmutable('2026-03-21 18:00'), new DateTimeImmutable('2026-03-21 20:00'));
        $subscription = new EventSubscription();
        $subscription->createdUser = $anna;
        $subscription->amount = 1;
        $subscription->event = $other;
        self::assertSame($addresses[0], $this->transformer()->transform($other, true)->getAttendees()[0]->getEmailAddress()->getEmailAddress());
    }
}
