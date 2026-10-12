<?php

namespace App\Recurrence;

use App\Entity\Event\Event;
use App\Entity\Event\RecurringEvent;
use App\Entity\Event\Tag;
use App\Entity\Image;
use App\Service\LocalDate;
use Doctrine\Common\Collections\ArrayCollection;
use DateTimeImmutable;
use Symfony\Component\Translation\TranslatableMessage;

use function Symfony\Component\Translation\t;

/**
 * A property a series hands down to its occurrences.
 *
 * Values are read in a normalised, comparable form (strings, numbers, sorted lists), so a series
 * edit can tell which occurrences actually change and an occurrence edit can tell which fields
 * the admin customised.
 */
enum SeriesField: string
{
    case Schedule = 'schedule';
    case Name = 'name';
    case Description = 'description';
    case Location = 'location';
    case Image = 'image';
    case Tags = 'tags';
    case SubscriptionOpenDate = 'subscriptionOpenDate';
    case SubscriptionDeadline = 'subscriptionDeadline';
    case SubscriberLimit = 'subscriberLimit';
    case MemberPrice = 'memberPrice';
    case GuestPrice = 'guestPrice';
    case GuestsAllowed = 'guestsAllowed';
    case Published = 'published';

    private const string DATE_FORMAT = 'Y-m-d H:i';

    public function label(): TranslatableMessage
    {
        return match ($this) {
            self::Schedule => t('Date and time'),
            self::Name => t('Name'),
            self::Description => t('Description'),
            self::Location => t('Location'),
            self::Image => t('Image'),
            self::Tags => t('Tags'),
            self::SubscriptionOpenDate => t('Subscriptions open'),
            self::SubscriptionDeadline => t('Subscription deadline'),
            self::SubscriberLimit => t('Subscriber limit'),
            self::MemberPrice => t('Member price'),
            self::GuestPrice => t('Guest price'),
            self::GuestsAllowed => t('Guests allowed'),
            self::Published => t('Published'),
        };
    }

    /**
     * Changes subscribers need to hear about.
     */
    public function concernsSubscribers(): bool
    {
        return $this === self::Schedule || $this === self::Location;
    }

    /**
     * Fields whose value is derived from each occurrence's start.
     */
    public function isRelativeToStart(): bool
    {
        return $this === self::Schedule || $this === self::SubscriptionOpenDate || $this === self::SubscriptionDeadline;
    }

    public function read(Event $event): mixed
    {
        return match ($this) {
            self::Schedule => [self::date($event->startDate), self::date($event->endDate)],
            self::Name => $event->name,
            self::Description => $event->description,
            self::Location => $event->location,
            self::Image => self::imageKey($event->image),
            self::Tags => self::tagNames($event->getTags()),
            self::SubscriptionOpenDate => self::date($event->subscriptionOpenDate),
            self::SubscriptionDeadline => self::date($event->subscriptionDeadline),
            self::SubscriberLimit => $event->subscriberLimit,
            self::MemberPrice => $event->getMemberPrice(),
            self::GuestPrice => $event->getGuestPrice(),
            self::GuestsAllowed => $event->guestsAllowed,
            self::Published => $event->published,
        };
    }

    /**
     * The value an occurrence of $series should have.
     *
     * @param DateTimeImmutable $slot  the occurrence slot the rule generated
     * @param DateTimeImmutable $start the start the occurrence actually has (differs from $slot when rescheduled)
     */
    public function expected(RecurringEvent $series, DateTimeImmutable $slot, DateTimeImmutable $start): mixed
    {
        return match ($this) {
            self::Schedule => [self::date($slot), self::date($series->endFor($slot))],
            self::Name => $series->name,
            self::Description => $series->description,
            self::Location => $series->location,
            self::Image => self::imageKey($series->image),
            self::Tags => self::tagNames($series->getTags()),
            self::SubscriptionOpenDate => self::date($series->subscriptionOpenFor($start)),
            self::SubscriptionDeadline => self::date($series->subscriptionDeadlineFor($start)),
            self::SubscriberLimit => $series->subscriberLimit,
            self::MemberPrice => $series->getMemberPrice(),
            self::GuestPrice => $series->getGuestPrice(),
            self::GuestsAllowed => $series->guestsAllowed,
            self::Published => $series->published,
        };
    }

    public function apply(RecurringEvent $series, DateTimeImmutable $slot, Event $event): void
    {
        match ($this) {
            self::Schedule => [$event->startDate, $event->endDate] = [$slot, $series->endFor($slot)],
            self::Name => $event->name = $series->name,
            self::Description => $event->description = $series->description,
            self::Location => $event->location = $series->location,
            self::Image => $event->image = $series->image,
            self::Tags => $event->setTags(new ArrayCollection($series->getTags()->toArray())),
            self::SubscriptionOpenDate => $event->subscriptionOpenDate = $series->subscriptionOpenFor($event->startDate),
            self::SubscriptionDeadline => $event->subscriptionDeadline = $series->subscriptionDeadlineFor($event->startDate),
            self::SubscriberLimit => $event->subscriberLimit = $series->subscriberLimit,
            self::MemberPrice => $event->setMemberPrice($series->getMemberPrice()),
            self::GuestPrice => $event->setGuestPrice($series->getGuestPrice()),
            self::GuestsAllowed => $event->guestsAllowed = $series->guestsAllowed,
            self::Published => $event->published = $series->published,
        };
    }

    /**
     * Renders a value returned by read() or expected() for a changelist. Fixed words come back
     * translatable, so templates render the result with |trans.
     */
    public function format(mixed $value): string|TranslatableMessage
    {
        if ($value === null || $value === '' || $value === []) {
            return '—';
        }

        return match ($this) {
            self::Schedule => self::formatSchedule($value[0], $value[1]),
            self::SubscriptionOpenDate, self::SubscriptionDeadline => self::formatDate($value),
            self::Description => mb_strimwidth(preg_replace('/\s+/', ' ', $value), 0, 60, '…'),
            self::Image => t('Image'),
            self::Tags => implode(', ', $value),
            self::MemberPrice, self::GuestPrice => '€ ' . number_format($value, 2, ',', '.'),
            self::GuestsAllowed, self::Published => $value ? t('Yes') : t('No'),
            default => (string) $value,
        };
    }

    /**
     * @return list<string> field values, so they can be stored as JSON
     */
    public static function values(self ...$fields): array
    {
        return array_values(array_unique(array_map(fn(self $field) => $field->value, $fields)));
    }

    private static function date(?\DateTimeInterface $date): ?string
    {
        return $date?->format(self::DATE_FORMAT);
    }

    private static function imageKey(?Image $image): ?string
    {
        return $image ? (string) ($image->id ?? 'new-' . spl_object_id($image)) : null;
    }

    /**
     * @param iterable<Tag> $tags
     * @return list<string>
     */
    private static function tagNames(iterable $tags): array
    {
        $names = [];
        foreach ($tags as $tag) {
            $names[] = $tag->name;
        }
        sort($names);

        return $names;
    }

    private static function formatDate(string $value): string
    {
        return LocalDate::format(DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $value), LocalDate::DATE_TIME);
    }

    private static function formatSchedule(string $start, string $end): string
    {
        $startDate = DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $start);
        $endDate = DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $end);
        $endFormat = $startDate->format('Y-m-d') === $endDate->format('Y-m-d') ? 'HH:mm' : LocalDate::DAY_MONTH_TIME;

        return LocalDate::format($startDate, LocalDate::DATE_TIME) . '–' . LocalDate::format($endDate, $endFormat);
    }
}
