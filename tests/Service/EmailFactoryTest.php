<?php

namespace App\Tests\Service;

use App\Entity\Event\Event;
use App\Entity\User;
use App\Service\EmailFactory;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

class EmailFactoryTest extends TestCase
{
    private function createUser(string $email = 'user@example.com'): User
    {
        $user = new User();
        $user->username = 'testuser';
        $user->name = 'Test User';
        $user->password = 'hashed';
        $user->email = $email;

        return $user;
    }

    private function createEvent(): Event
    {
        $event = new Event();
        $event->name = 'Test Event';
        $event->location = 'Here';
        $event->startDate = new DateTimeImmutable('+1 week');
        $event->duration = new DateInterval('PT2H');

        return $event;
    }

    // --- signupEmail ---

    public function testSignupEmailSendsToUser(): void
    {
        $user = $this->createUser('alice@example.com');
        $email = EmailFactory::signupEmail($user);

        self::assertInstanceOf(TemplatedEmail::class, $email);
        self::assertSame('alice@example.com', $email->getTo()[0]->getAddress());
    }

    public function testSignupEmailHasSubject(): void
    {
        $email = EmailFactory::signupEmail($this->createUser());

        self::assertSame('Durf - Account created', $email->getSubject());
    }

    public function testSignupEmailUsesCorrectTemplate(): void
    {
        /** @var TemplatedEmail $email */
        $email = EmailFactory::signupEmail($this->createUser());

        self::assertSame('email/user/signup.html.twig', $email->getHtmlTemplate());
    }

    public function testSignupEmailContextContainsUser(): void
    {
        $user = $this->createUser();
        /** @var TemplatedEmail $email */
        $email = EmailFactory::signupEmail($user);
        $context = $email->getContext();

        self::assertSame($user, $context['user']);
        self::assertArrayHasKey('title', $context);
    }

    // --- eventGuestSignupEmail ---

    public function testEventGuestSignupEmailSendsToUser(): void
    {
        $user = $this->createUser('guest@example.com');
        $event = $this->createEvent();
        $email = EmailFactory::eventGuestSignupEmail($user, $event);

        self::assertSame('guest@example.com', $email->getTo()[0]->getAddress());
    }

    public function testEventGuestSignupEmailHasSubject(): void
    {
        $email = EmailFactory::eventGuestSignupEmail($this->createUser(), $this->createEvent());

        self::assertSame('Durf - Guest account created', $email->getSubject());
    }

    public function testEventGuestSignupEmailUsesCorrectTemplate(): void
    {
        /** @var TemplatedEmail $email */
        $email = EmailFactory::eventGuestSignupEmail($this->createUser(), $this->createEvent());

        self::assertSame('email/user/signup.guest.html.twig', $email->getHtmlTemplate());
    }

    public function testEventGuestSignupEmailContextContainsEvent(): void
    {
        $event = $this->createEvent();
        /** @var TemplatedEmail $email */
        $email = EmailFactory::eventGuestSignupEmail($this->createUser(), $event);

        self::assertSame($event, $email->getContext()['event']);
    }

    // --- resetPassword ---

    public function testResetPasswordEmailHasSubject(): void
    {
        $email = EmailFactory::resetPassword($this->createUser(), 'token123');

        self::assertSame('Durf - Password reset request', $email->getSubject());
    }

    public function testResetPasswordEmailUsesCorrectTemplate(): void
    {
        /** @var TemplatedEmail $email */
        $email = EmailFactory::resetPassword($this->createUser(), 'token123');

        self::assertSame('email/user/reset.password.html.twig', $email->getHtmlTemplate());
    }

    public function testResetPasswordEmailContextContainsToken(): void
    {
        /** @var TemplatedEmail $email */
        $email = EmailFactory::resetPassword($this->createUser(), 'my-reset-token');

        self::assertSame('my-reset-token', $email->getContext()['resetToken']);
    }

    // --- userEnabled ---

    public function testUserEnabledEmailHasSubject(): void
    {
        $email = EmailFactory::userEnabled($this->createUser());

        self::assertSame('Durf - Account enabled', $email->getSubject());
    }

    public function testUserEnabledEmailUsesCorrectTemplate(): void
    {
        /** @var TemplatedEmail $email */
        $email = EmailFactory::userEnabled($this->createUser());

        self::assertSame('email/user/enabled.html.twig', $email->getHtmlTemplate());
    }

    // --- newUserNotification ---

    public function testNewUserNotificationHasNoRecipient(): void
    {
        $email = EmailFactory::newUserNotification($this->createUser());

        self::assertEmpty($email->getTo());
    }

    public function testNewUserNotificationSubjectContainsUserName(): void
    {
        $user = $this->createUser();
        $email = EmailFactory::newUserNotification($user);

        self::assertStringContainsString('Test User', $email->getSubject());
    }

    public function testNewUserNotificationUsesCorrectTemplate(): void
    {
        /** @var TemplatedEmail $email */
        $email = EmailFactory::newUserNotification($this->createUser());

        self::assertSame('email/user/new_user_notification.html.twig', $email->getHtmlTemplate());
    }

    public function testNewUserNotificationContextContainsUser(): void
    {
        $user = $this->createUser();
        /** @var TemplatedEmail $email */
        $email = EmailFactory::newUserNotification($user);

        self::assertSame($user, $email->getContext()['user']);
    }
}
