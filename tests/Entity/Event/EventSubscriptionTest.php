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
        $event->setName('Test Event');
        $event->setLocation('Location');
        $event->setStartDate(new DateTimeImmutable('+1 week'));
        $event->setDuration(new DateInterval('PT2H'));
        if ($limit > 0) {
            $event->setSubscriberLimit($limit);
        }

        return $event;
    }

    private function createUser(string $name = 'user'): User
    {
        $user = new User();
        $user->setUsername($name);
        $user->setName($name);
        $user->setPassword('hashed');

        return $user;
    }

    private function createSubscription(Event $event, User $user, int $amount = 1): EventSubscription
    {
        $sub = new EventSubscription();
        $sub->setCreatedUser($user);
        $sub->setAmount($amount);
        $sub->setEvent($event);

        return $sub;
    }

    // --- Basic Properties ---

    public function testSetAndGetEvent(): void
    {
        $event = $this->createEvent();
        $sub = new EventSubscription();
        $sub->setEvent($event);

        self::assertSame($event, $sub->getEvent());
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
        $sub->setAmount(3);
        self::assertSame(3, $sub->getAmount());
    }

    public function testSetAndGetNote(): void
    {
        $sub = new EventSubscription();
        $sub->setNote('A note');
        self::assertSame('A note', $sub->getNote());
    }

    public function testNoteNullByDefault(): void
    {
        $sub = new EventSubscription();
        self::assertNull($sub->getNote());
    }

    // --- Validation (unit level, without Symfony validator) ---

    public function testSubscriptionTracksCreatedUser(): void
    {
        $user = $this->createUser();
        $sub = new EventSubscription();
        $sub->setCreatedUser($user);

        self::assertSame($user, $sub->getCreatedUser());
    }

    public function testQuestionAnswersEmptyByDefault(): void
    {
        $sub = new EventSubscription();
        self::assertCount(0, $sub->questionAnswers);
    }
}
