<?php

namespace App\Tests\Entity\Event;

use App\Entity\Event\Event;
use App\Entity\Event\EventSubscription;
use App\Entity\User;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{
    private function createEvent(): Event
    {
        $event = new Event();
        $event->name = 'Test Event';
        $event->location = 'Test Location';
        $event->startDate = new DateTimeImmutable('+1 week');
        $event->duration = new DateInterval('PT2H');

        return $event;
    }

    private function createUser(string $name = 'testuser'): User
    {
        $user = new User();
        $user->username = $name;
        $user->name = $name;
        $user->password = 'hashed';

        return $user;
    }

    private function subscribeUser(Event $event, User $user, int $amount = 1): EventSubscription
    {
        $subscription = new EventSubscription();
        $subscription->createdUser = $user;
        $subscription->amount = $amount;
        $subscription->event = $event;

        return $subscription;
    }

    // --- Subscription Open Date ---

    public function testIsPastSubscriptionOpenDateNullMeansOpen(): void
    {
        $event = $this->createEvent();
        self::assertNull($event->subscriptionOpenDate);
        self::assertTrue($event->isPastSubscriptionOpenDate());
    }

    public function testIsPastSubscriptionOpenDateInThePast(): void
    {
        $event = $this->createEvent();
        $event->subscriptionOpenDate = new DateTimeImmutable('-1 day');
        self::assertTrue($event->isPastSubscriptionOpenDate());
    }

    public function testIsPastSubscriptionOpenDateInTheFuture(): void
    {
        $event = $this->createEvent();
        $event->subscriptionOpenDate = new DateTimeImmutable('+1 day');
        self::assertFalse($event->isPastSubscriptionOpenDate());
    }

    public function testIsPastSubscriptionOpenDateWithExplicitDate(): void
    {
        $event = $this->createEvent();
        $event->subscriptionOpenDate = new DateTimeImmutable('2025-06-01');

        $before = new \DateTime('2025-05-31');
        $after = new \DateTime('2025-06-02');

        self::assertFalse($event->isPastSubscriptionOpenDate($before));
        self::assertTrue($event->isPastSubscriptionOpenDate($after));
    }

    // --- Subscription Deadline ---

    public function testIsNotPastSubscriptionDeadlineNullMeansOpen(): void
    {
        $event = $this->createEvent();
        self::assertNull($event->subscriptionDeadline);
        self::assertTrue($event->isNotPastSubscriptionDeadline());
    }

    public function testIsNotPastSubscriptionDeadlineInTheFuture(): void
    {
        $event = $this->createEvent();
        $event->subscriptionDeadline = new DateTimeImmutable('+1 day');
        self::assertTrue($event->isNotPastSubscriptionDeadline());
    }

    public function testIsNotPastSubscriptionDeadlineInThePast(): void
    {
        $event = $this->createEvent();
        $event->subscriptionDeadline = new DateTimeImmutable('-1 day');
        self::assertFalse($event->isNotPastSubscriptionDeadline());
    }

    // --- Start Date ---

    public function testIsNotPastStartDateFutureEvent(): void
    {
        $event = $this->createEvent();
        self::assertTrue($event->isNotPastStartDate());
    }

    public function testIsNotPastStartDatePastEvent(): void
    {
        $event = $this->createEvent();
        $event->startDate = new DateTimeImmutable('-1 day');
        self::assertFalse($event->isNotPastStartDate());
    }

    // --- Subscriptions ---

    public function testIsSubscribedFalseByDefault(): void
    {
        $event = $this->createEvent();
        $user = $this->createUser();
        self::assertFalse($event->isSubscribed($user));
    }

    public function testIsSubscribedTrueAfterSubscribing(): void
    {
        $event = $this->createEvent();
        $user = $this->createUser();
        $this->subscribeUser($event, $user);

        self::assertTrue($event->isSubscribed($user));
    }

    public function testGetSubscriptionReturnsNullForNonSubscriber(): void
    {
        $event = $this->createEvent();
        $user = $this->createUser();
        self::assertNull($event->getSubscription($user));
    }

    public function testGetSubscriptionReturnsNullForNull(): void
    {
        $event = $this->createEvent();
        self::assertNull($event->getSubscription(null));
    }

    public function testGetSubscriptionReturnsSubscription(): void
    {
        $event = $this->createEvent();
        $user = $this->createUser();
        $subscription = $this->subscribeUser($event, $user);

        self::assertSame($subscription, $event->getSubscription($user));
    }

    public function testAmountOfSubscriptionsEmpty(): void
    {
        $event = $this->createEvent();
        self::assertSame(0, $event->getAmountOfSubscriptions());
    }

    public function testAmountOfSubscriptionsSumsAmounts(): void
    {
        $event = $this->createEvent();
        $this->subscribeUser($event, $this->createUser('user1'), 2);
        $this->subscribeUser($event, $this->createUser('user2'), 3);

        self::assertSame(5, $event->getAmountOfSubscriptions());
    }

    public function testRemoveEventSubscription(): void
    {
        $event = $this->createEvent();
        $user = $this->createUser();
        $subscription = $this->subscribeUser($event, $user);

        $event->removeEventSubscription($subscription);
        self::assertFalse($event->isSubscribed($user));
        self::assertSame(0, $event->getAmountOfSubscriptions());
    }

    // --- Subscriber Limit ---

    public function testSubscriberLimitNullByDefault(): void
    {
        $event = $this->createEvent();
        self::assertNull($event->subscriberLimit);
    }

    public function testSubscriberLimitCanBeSet(): void
    {
        $event = $this->createEvent();
        $event->subscriberLimit = 10;
        self::assertSame(10, $event->subscriberLimit);
    }

    // --- Guests ---

    public function testGuestsNotAllowedByDefault(): void
    {
        $event = $this->createEvent();
        self::assertFalse($event->guestsAllowed);
    }

    public function testGuestsAllowed(): void
    {
        $event = $this->createEvent();
        $event->guestsAllowed = true;
        self::assertTrue($event->guestsAllowed);
    }

    // --- Pricing ---

    public function testMemberPriceDefaultsToZero(): void
    {
        $event = $this->createEvent();
        self::assertSame(0.0, $event->getMemberPrice());
    }

    public function testMemberPriceStoredAsCents(): void
    {
        $event = $this->createEvent();
        $event->setMemberPrice(12.50);
        self::assertSame(12.50, $event->getMemberPrice());
    }

    public function testGuestPriceNullByDefault(): void
    {
        $event = $this->createEvent();
        self::assertNull($event->getGuestPrice());
    }

    // --- Copy From ---

    public function testCopyFromCopiesProperties(): void
    {
        $source = $this->createEvent();
        $source->name = 'Source Event';
        $source->description = 'A description';
        $source->location = 'Source Location';
        $source->setMemberPrice(5.00);
        $source->guestsAllowed = true;
        $source->subscriberLimit = 20;
        $source->subscriptionDeadline = new DateTimeImmutable('+3 days');

        $target = new Event();
        $target->copyFrom($source);

        self::assertSame('Source Event', $target->name);
        self::assertSame('A description', $target->description);
        self::assertSame('Source Location', $target->location);
        self::assertSame(5.00, $target->getMemberPrice());
        self::assertTrue($target->guestsAllowed);
        self::assertSame(20, $target->subscriberLimit);
        self::assertNotNull($target->subscriptionDeadline);
    }

    public function testCopyFromCopiesStartTime(): void
    {
        $source = $this->createEvent();
        $source->startDate = new DateTimeImmutable('2025-06-01 14:30:00');

        $target = new Event();
        $target->copyFrom($source);

        self::assertSame('14', $target->startDate->format('H'));
        self::assertSame('30', $target->startDate->format('i'));
    }

    // --- Tags ---

    public function testTagsEmptyByDefault(): void
    {
        $event = $this->createEvent();
        self::assertCount(0, $event->getTags());
    }

    // --- Recurring Event relationship ---

    public function testRecurringEventNullByDefault(): void
    {
        $event = $this->createEvent();
        self::assertNull($event->recurringEvent);
    }

    // --- Description ---

    public function testDescriptionDefaultsToEmptyString(): void
    {
        $event = $this->createEvent();
        self::assertSame('', $event->description);
    }

    public function testDescriptionCanBeSetToNull(): void
    {
        $event = $this->createEvent();
        $event->description = 'something';
        $event->description = null;
        self::assertSame('', $event->description);
    }

    // --- Published ---

    public function testPublishedTrueByDefault(): void
    {
        $event = new Event();
        self::assertTrue($event->published);
    }

    public function testCopyFromCopiesPublishedState(): void
    {
        $source = $this->createEvent();
        $source->published = false;

        $target = new Event();
        $target->copyFrom($source);

        self::assertFalse($target->published);
    }
}
