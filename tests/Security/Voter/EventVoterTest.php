<?php

namespace App\Tests\Security\Voter;

use App\Entity\Event\Event;
use App\Entity\Event\EventSubscription;
use App\Entity\User;
use App\Security\Voter\EventVoter;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class EventVoterTest extends TestCase
{
    private function createVoter(bool $isEventAdmin = false): EventVoter
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')
            ->willReturnCallback(fn(string $role) => match ($role) {
                'ROLE_EVENT_ADMIN' => $isEventAdmin,
                default => false,
            });

        return new EventVoter($security);
    }

    private function createToken(User $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }

    private function createUser(string $name = 'user', bool $guest = false): User
    {
        $user = new User();
        $user->setUsername($name);
        $user->setName($name);
        $user->setPassword('hashed');
        $user->setGuest($guest);

        return $user;
    }

    private function createEvent(
        string $startDate = '+1 week',
        ?string $deadline = null,
        ?string $openDate = null,
        bool $guestsAllowed = false,
    ): Event {
        $event = new Event();
        $event->setName('Event');
        $event->setLocation('Here');
        $event->setStartDate(new DateTimeImmutable($startDate));
        $event->setDuration(new DateInterval('PT2H'));
        $event->setGuestsAllowed($guestsAllowed);

        if ($deadline !== null) {
            $event->setSubscriptionDeadline(new DateTimeImmutable($deadline));
        }
        if ($openDate !== null) {
            $event->setSubscriptionOpenDate(new DateTimeImmutable($openDate));
        }

        return $event;
    }

    private function subscribeUser(Event $event, User $user): void
    {
        $sub = new EventSubscription();
        $sub->setCreatedUser($user);
        $sub->setAmount(1);
        $sub->setEvent($event);
    }

    private function vote(EventVoter $voter, string $attribute, Event $event, User $user): bool
    {
        $method = new \ReflectionMethod(EventVoter::class, 'voteOnAttribute');
        return $method->invoke($voter, $attribute, $event, $this->createToken($user));
    }

    // ==========================================
    // SUBSCRIBE - Regular User
    // ==========================================

    public function testSubscribeAllowedForFutureEvent(): void
    {
        $voter = $this->createVoter();
        $user = $this->createUser();
        $event = $this->createEvent('+1 week');

        self::assertTrue($this->vote($voter, 'subscribe', $event, $user));
    }

    public function testSubscribeDeniedForPastEvent(): void
    {
        $voter = $this->createVoter();
        $user = $this->createUser();
        $event = $this->createEvent('-1 day');

        self::assertFalse($this->vote($voter, 'subscribe', $event, $user));
    }

    public function testSubscribeDeniedWhenAlreadySubscribed(): void
    {
        $voter = $this->createVoter();
        $user = $this->createUser();
        $event = $this->createEvent('+1 week');
        $this->subscribeUser($event, $user);

        self::assertFalse($this->vote($voter, 'subscribe', $event, $user));
    }

    public function testSubscribeDeniedWhenPastDeadline(): void
    {
        $voter = $this->createVoter();
        $user = $this->createUser();
        $event = $this->createEvent('+1 week', '-1 day');

        self::assertFalse($this->vote($voter, 'subscribe', $event, $user));
    }

    public function testSubscribeAllowedWhenBeforeDeadline(): void
    {
        $voter = $this->createVoter();
        $user = $this->createUser();
        $event = $this->createEvent('+1 week', '+3 days');

        self::assertTrue($this->vote($voter, 'subscribe', $event, $user));
    }

    // ==========================================
    // SUBSCRIBE - Subscription Open Date
    // ==========================================

    public function testSubscribeDeniedBeforeOpenDate(): void
    {
        $voter = $this->createVoter();
        $user = $this->createUser();
        $event = $this->createEvent('+2 weeks', null, '+1 week');

        self::assertFalse($this->vote($voter, 'subscribe', $event, $user));
    }

    public function testSubscribeAllowedAfterOpenDate(): void
    {
        $voter = $this->createVoter();
        $user = $this->createUser();
        $event = $this->createEvent('+2 weeks', null, '-1 day');

        self::assertTrue($this->vote($voter, 'subscribe', $event, $user));
    }

    public function testSubscribeAllowedWhenNoOpenDate(): void
    {
        $voter = $this->createVoter();
        $user = $this->createUser();
        $event = $this->createEvent('+1 week');

        self::assertTrue($this->vote($voter, 'subscribe', $event, $user));
    }

    public function testSubscribeDeniedBeforeOpenDateEvenIfDeadlineOpen(): void
    {
        $voter = $this->createVoter();
        $user = $this->createUser();
        $event = $this->createEvent('+2 weeks', '+1 week', '+3 days');

        self::assertFalse($this->vote($voter, 'subscribe', $event, $user));
    }

    // ==========================================
    // SUBSCRIBE - Guest Users
    // ==========================================

    public function testSubscribeDeniedForGuestWhenGuestsNotAllowed(): void
    {
        $voter = $this->createVoter();
        $user = $this->createUser('guest', guest: true);
        $event = $this->createEvent('+1 week', guestsAllowed: false);

        self::assertFalse($this->vote($voter, 'subscribe', $event, $user));
    }

    public function testSubscribeAllowedForGuestWhenGuestsAllowed(): void
    {
        $voter = $this->createVoter();
        $user = $this->createUser('guest', guest: true);
        $event = $this->createEvent('+1 week', guestsAllowed: true);

        self::assertTrue($this->vote($voter, 'subscribe', $event, $user));
    }

    public function testSubscribeDeniedForGuestEvenWithGuestsAllowedIfPastDeadline(): void
    {
        $voter = $this->createVoter();
        $user = $this->createUser('guest', guest: true);
        $event = $this->createEvent('+1 week', '-1 day', guestsAllowed: true);

        self::assertFalse($this->vote($voter, 'subscribe', $event, $user));
    }

    // ==========================================
    // SUBSCRIBE - Admin Bypass
    // ==========================================

    public function testSubscribeAlwaysAllowedForAdmin(): void
    {
        $voter = $this->createVoter(isEventAdmin: true);
        $user = $this->createUser();
        $event = $this->createEvent('-1 day'); // past event

        self::assertTrue($this->vote($voter, 'subscribe', $event, $user));
    }

    public function testSubscribeAllowedForAdminEvenBeforeOpenDate(): void
    {
        $voter = $this->createVoter(isEventAdmin: true);
        $user = $this->createUser();
        $event = $this->createEvent('+2 weeks', null, '+1 week');

        self::assertTrue($this->vote($voter, 'subscribe', $event, $user));
    }

    public function testSubscribeAllowedForAdminEvenPastDeadline(): void
    {
        $voter = $this->createVoter(isEventAdmin: true);
        $user = $this->createUser();
        $event = $this->createEvent('+1 week', '-1 day');

        self::assertTrue($this->vote($voter, 'subscribe', $event, $user));
    }

    public function testSubscribeAllowedForAdminEvenWhenAlreadySubscribed(): void
    {
        $voter = $this->createVoter(isEventAdmin: true);
        $user = $this->createUser();
        $event = $this->createEvent('+1 week');
        $this->subscribeUser($event, $user);

        self::assertTrue($this->vote($voter, 'subscribe', $event, $user));
    }

    // ==========================================
    // UNSUBSCRIBE - Regular User
    // ==========================================

    public function testUnsubscribeAllowedWhenSubscribedAndFutureEvent(): void
    {
        $voter = $this->createVoter();
        $user = $this->createUser();
        $event = $this->createEvent('+1 week');
        $this->subscribeUser($event, $user);

        self::assertTrue($this->vote($voter, 'unsubscribe', $event, $user));
    }

    public function testUnsubscribeDeniedWhenNotSubscribed(): void
    {
        $voter = $this->createVoter();
        $user = $this->createUser();
        $event = $this->createEvent('+1 week');

        self::assertFalse($this->vote($voter, 'unsubscribe', $event, $user));
    }

    public function testUnsubscribeDeniedAfterEventStarted(): void
    {
        $voter = $this->createVoter();
        $user = $this->createUser();
        $event = $this->createEvent('-1 day');
        $this->subscribeUser($event, $user);

        self::assertFalse($this->vote($voter, 'unsubscribe', $event, $user));
    }

    // ==========================================
    // UNSUBSCRIBE - Admin
    // ==========================================

    public function testUnsubscribeAllowedForAdmin(): void
    {
        $voter = $this->createVoter(isEventAdmin: true);
        $user = $this->createUser();
        $event = $this->createEvent('-1 day'); // past event

        self::assertTrue($this->vote($voter, 'unsubscribe', $event, $user));
    }

    // ==========================================
    // Edge cases
    // ==========================================

    public function testAnonymousUserDenied(): void
    {
        $voter = $this->createVoter();
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $method = new \ReflectionMethod(EventVoter::class, 'voteOnAttribute');
        $event = $this->createEvent('+1 week');

        self::assertFalse($method->invoke($voter, 'subscribe', $event, $token));
    }

    public function testCombinedOpenDateAndDeadlineWindow(): void
    {
        $voter = $this->createVoter();
        $user = $this->createUser();

        // Open date in the past, deadline in the future = allowed
        $event = $this->createEvent('+2 weeks', '+1 week', '-1 day');
        self::assertTrue($this->vote($voter, 'subscribe', $event, $user));
    }

    public function testAllConditionsMustBeMetForSubscribe(): void
    {
        $voter = $this->createVoter();
        $user = $this->createUser();

        // Past open date but past deadline
        $event = $this->createEvent('+2 weeks', '-1 day', '-2 days');
        self::assertFalse($this->vote($voter, 'subscribe', $event, $user));
    }
}
