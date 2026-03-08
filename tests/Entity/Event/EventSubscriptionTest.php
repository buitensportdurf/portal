<?php

namespace App\Tests\Entity\Event;

use App\Entity\Event\Event;
use App\Entity\Event\EventSubscription;
use App\Entity\User;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class EventSubscriptionTest extends TestCase
{
    private function createEvent(int $limit = 0): Event
    {
        $event = new Event();
        $event->name = 'Test Event';
        $event->location = 'Location';
        $event->startDate = new DateTimeImmutable('+1 week');
        $event->duration = new DateInterval('PT2H');
        if ($limit > 0) {
            $event->subscriberLimit = $limit;
        }

        return $event;
    }

    private function createUser(string $name = 'user'): User
    {
        $user = new User();
        $user->username = $name;
        $user->name = $name;
        $user->password = 'hashed';

        return $user;
    }

    private function createSubscription(Event $event, User $user, int $amount = 1): EventSubscription
    {
        $sub = new EventSubscription();
        $sub->createdUser = $user;
        $sub->amount = $amount;
        $sub->event = $event;

        return $sub;
    }

    // --- Basic Properties ---

    public function testSetAndGetEvent(): void
    {
        $event = $this->createEvent();
        $sub = new EventSubscription();
        $sub->event = $event;

        self::assertSame($event, $sub->event);
    }

    public function testSetEventAlsoAddsToEventCollection(): void
    {
        $event = $this->createEvent();
        $user = $this->createUser();
        $sub = $this->createSubscription($event, $user);

        self::assertTrue($event->getEventSubscriptions()->contains($sub));
    }

    public function testSetAndGetAmount(): void
    {
        $sub = new EventSubscription();
        $sub->amount = 3;
        self::assertSame(3, $sub->amount);
    }

    public function testSetAndGetNote(): void
    {
        $sub = new EventSubscription();
        $sub->note = 'A note';
        self::assertSame('A note', $sub->note);
    }

    public function testNoteNullByDefault(): void
    {
        $sub = new EventSubscription();
        self::assertNull($sub->note);
    }

    // --- Validation (unit level, without Symfony validator) ---

    public function testSubscriptionTracksCreatedUser(): void
    {
        $user = $this->createUser();
        $sub = new EventSubscription();
        $sub->createdUser = $user;

        self::assertSame($user, $sub->createdUser);
    }

    public function testQuestionAnswersEmptyByDefault(): void
    {
        $sub = new EventSubscription();
        self::assertCount(0, $sub->questionAnswers);
    }
}
