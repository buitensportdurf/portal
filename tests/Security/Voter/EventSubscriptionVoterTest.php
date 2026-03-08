<?php

namespace App\Tests\Security\Voter;

use App\Entity\Event\Event;
use App\Entity\Event\EventSubscription;
use App\Entity\User;
use App\Security\Voter\EventSubscriptionVoter;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class EventSubscriptionVoterTest extends TestCase
{
    private function createVoter(bool $isEventAdmin = false): EventSubscriptionVoter
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')
            ->willReturnCallback(fn(string $role) => match ($role) {
                'ROLE_EVENT_ADMIN' => $isEventAdmin,
                default => false,
            });

        return new EventSubscriptionVoter($security);
    }

    private function createToken(User $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }

    private function createUser(string $name = 'user'): User
    {
        $user = new User();
        $user->username = $name;
        $user->name = $name;
        $user->password = 'hashed';

        return $user;
    }

    private function createSubscription(User $owner): EventSubscription
    {
        $event = new Event();
        $event->name = 'Event';
        $event->location = 'Here';
        $event->startDate = new DateTimeImmutable('+1 week');
        $event->duration = new DateInterval('PT2H');

        $sub = new EventSubscription();
        $sub->createdUser = $owner;
        $sub->amount = 1;
        $sub->event = $event;

        return $sub;
    }

    private function vote(EventSubscriptionVoter $voter, string $attribute, EventSubscription $sub, User $user): bool
    {
        $method = new \ReflectionMethod(EventSubscriptionVoter::class, 'voteOnAttribute');
        return $method->invoke($voter, $attribute, $sub, $this->createToken($user));
    }

    public function testEditAllowedByOwner(): void
    {
        $voter = $this->createVoter();
        $owner = $this->createUser('owner');
        $sub = $this->createSubscription($owner);

        self::assertTrue($this->vote($voter, 'edit', $sub, $owner));
    }

    public function testEditDeniedForOtherUser(): void
    {
        $voter = $this->createVoter();
        $owner = $this->createUser('owner');
        $other = $this->createUser('other');
        $sub = $this->createSubscription($owner);

        self::assertFalse($this->vote($voter, 'edit', $sub, $other));
    }

    public function testEditAllowedForAdmin(): void
    {
        $voter = $this->createVoter(isEventAdmin: true);
        $owner = $this->createUser('owner');
        $admin = $this->createUser('admin');
        $sub = $this->createSubscription($owner);

        self::assertTrue($this->vote($voter, 'edit', $sub, $admin));
    }

    public function testAnonymousUserDenied(): void
    {
        $voter = $this->createVoter();
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $owner = $this->createUser();
        $sub = $this->createSubscription($owner);

        $method = new \ReflectionMethod(EventSubscriptionVoter::class, 'voteOnAttribute');
        self::assertFalse($method->invoke($voter, 'edit', $sub, $token));
    }
}
