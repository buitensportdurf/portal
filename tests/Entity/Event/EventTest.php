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
        $event->setName('Test Event');
        $event->setLocation('Test Location');
        $event->setStartDate(new DateTimeImmutable('+1 week'));
        $event->setDuration(new DateInterval('PT2H'));

        return $event;
    }

    private function createUser(string $name = 'testuser'): User
    {
        $user = new User();
        $user->setUsername($name);
        $user->setName($name);
        $user->setPassword('hashed');

        return $user;
    }

    private function subscribeUser(Event $event, User $user, int $amount = 1): EventSubscription
    {
        $subscription = new EventSubscription();
        $subscription->setCreatedUser($user);
        $subscription->setAmount($amount);
        $subscription->setEvent($event);

        return $subscription;
    }

    // --- Subscription Open Date ---

    public function testIsPastSubscriptionOpenDateNullMeansOpen(): void
    {
        $event = $this->createEvent();
        self::assertNull($event->getSubscriptionOpenDate());
        self::assertTrue($event->isPastSubscriptionOpenDate());
    }

    public function testIsPastSubscriptionOpenDateInThePast(): void
    {
        $event = $this->createEvent();
        $event->setSubscriptionOpenDate(new DateTimeImmutable('-1 day'));
        self::assertTrue($event->isPastSubscriptionOpenDate());
    }

    public function testIsPastSubscriptionOpenDateInTheFuture(): void
    {
        $event = $this->createEvent();
        $event->setSubscriptionOpenDate(new DateTimeImmutable('+1 day'));
        self::assertFalse($event->isPastSubscriptionOpenDate());
    }

    public function testIsPastSubscriptionOpenDateWithExplicitDate(): void
    {
        $event = $this->createEvent();
        $event->setSubscriptionOpenDate(new DateTimeImmutable('2025-06-01'));

        $before = new \DateTime('2025-05-31');
        $after = new \DateTime('2025-06-02');

        self::assertFalse($event->isPastSubscriptionOpenDate($before));
        self::assertTrue($event->isPastSubscriptionOpenDate($after));
    }

    // --- Subscription Deadline ---

    public function testIsNotPastSubscriptionDeadlineNullMeansOpen(): void
    {
        $event = $this->createEvent();
        self::assertNull($event->getSubscriptionDeadline());
        self::assertTrue($event->isNotPastSubscriptionDeadline());
    }

    public function testIsNotPastSubscriptionDeadlineInTheFuture(): void
    {
        $event = $this->createEvent();
        $event->setSubscriptionDeadline(new DateTimeImmutable('+1 day'));
        self::assertTrue($event->isNotPastSubscriptionDeadline());
    }

    public function testIsNotPastSubscriptionDeadlineInThePast(): void
    {
        $event = $this->createEvent();
        $event->setSubscriptionDeadline(new DateTimeImmutable('-1 day'));
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
        $event->setStartDate(new DateTimeImmutable('-1 day'));
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
        self::assertNull($event->getSubscriberLimit());
    }

    public function testSubscriberLimitCanBeSet(): void
    {
        $event = $this->createEvent();
        $event->setSubscriberLimit(10);
        self::assertSame(10, $event->getSubscriberLimit());
    }

    // --- Guests ---

    public function testGuestsNotAllowedByDefault(): void
    {
        $event = $this->createEvent();
        self::assertFalse($event->isGuestsAllowed());
    }

    public function testGuestsAllowed(): void
    {
        $event = $this->createEvent();
        $event->setGuestsAllowed(true);
        self::assertTrue($event->isGuestsAllowed());
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
        $source->setName('Source Event');
        $source->setDescription('A description');
        $source->setLocation('Source Location');
        $source->setMemberPrice(5.00);
        $source->setGuestsAllowed(true);
        $source->setSubscriberLimit(20);
        $source->setSubscriptionDeadline(new DateTimeImmutable('+3 days'));

        $target = new Event();
        $target->copyFrom($source);

        self::assertSame('Source Event', $target->getName());
        self::assertSame('A description', $target->getDescription());
        self::assertSame('Source Location', $target->getLocation());
        self::assertSame(5.00, $target->getMemberPrice());
        self::assertTrue($target->isGuestsAllowed());
        self::assertSame(20, $target->getSubscriberLimit());
        self::assertNotNull($target->getSubscriptionDeadline());
    }

    public function testCopyFromCopiesStartTime(): void
    {
        $source = $this->createEvent();
        $source->setStartDate(new DateTimeImmutable('2025-06-01 14:30:00'));

        $target = new Event();
        $target->copyFrom($source);

        self::assertSame('14', $target->getStartDate()->format('H'));
        self::assertSame('30', $target->getStartDate()->format('i'));
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
        self::assertNull($event->getRecurringEvent());
    }

    // --- Description ---

    public function testDescriptionDefaultsToEmptyString(): void
    {
        $event = $this->createEvent();
        self::assertSame('', $event->getDescription());
    }

    public function testDescriptionCanBeSetToNull(): void
    {
        $event = $this->createEvent();
        $event->setDescription(null);
        self::assertSame('', $event->getDescription());
    }
}
