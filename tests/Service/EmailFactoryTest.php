<?php

namespace App\Tests\Service;

use App\Entity\Event\Event;
use App\Entity\Event\EventSubscription;
use App\Entity\User;
use App\Service\EmailFactory;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Contracts\Translation\TranslatorInterface;

class EmailFactoryTest extends TestCase
{
    /** @var list<?string> Locales the subjects were translated in */
    private array $translatedIn = [];

    private function factory(): EmailFactory
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(function (string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string {
            $this->translatedIn[] = $locale;

            return \MessageFormatter::formatMessage('en', $id, $parameters);
        });

        return new EmailFactory($translator, 'en');
    }

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
        $event->endDate = $event->startDate->add(new DateInterval('PT2H'));

        return $event;
    }

    // --- signupEmail ---

    public function testSignupEmailSendsToUser(): void
    {
        $user = $this->createUser('alice@example.com');
        $email = $this->factory()->signupEmail($user);

        self::assertInstanceOf(TemplatedEmail::class, $email);
        self::assertSame('alice@example.com', $email->getTo()[0]->getAddress());
    }

    public function testSignupEmailHasSubject(): void
    {
        $email = $this->factory()->signupEmail($this->createUser());

        self::assertSame('Durf - Account created', $email->getSubject());
    }

    public function testSignupEmailUsesCorrectTemplate(): void
    {
        /** @var TemplatedEmail $email */
        $email = $this->factory()->signupEmail($this->createUser());

        self::assertSame('email/user/signup.html.twig', $email->getHtmlTemplate());
    }

    public function testSignupEmailContextContainsUser(): void
    {
        $user = $this->createUser();
        /** @var TemplatedEmail $email */
        $email = $this->factory()->signupEmail($user);
        $context = $email->getContext();

        self::assertSame($user, $context['user']);
        self::assertArrayHasKey('title', $context);
    }

    // --- eventGuestSignupEmail ---

    public function testEventGuestSignupEmailSendsToUser(): void
    {
        $user = $this->createUser('guest@example.com');
        $event = $this->createEvent();
        $email = $this->factory()->eventGuestSignupEmail($user, $event, 'https://example.test/login');

        self::assertSame('guest@example.com', $email->getTo()[0]->getAddress());
    }

    public function testEventGuestSignupEmailHasSubject(): void
    {
        $email = $this->factory()->eventGuestSignupEmail($this->createUser(), $this->createEvent(), 'https://example.test/login');

        self::assertStringStartsWith('Durf - Subscribe to ', $email->getSubject());
        self::assertSame('https://example.test/login', $email->getContext()['loginUrl']);
    }

    public function testEventGuestSignupEmailUsesCorrectTemplate(): void
    {
        /** @var TemplatedEmail $email */
        $email = $this->factory()->eventGuestSignupEmail($this->createUser(), $this->createEvent(), 'https://example.test/login');

        self::assertSame('email/user/signup.guest.html.twig', $email->getHtmlTemplate());
    }

    public function testEventGuestSignupEmailContextContainsEvent(): void
    {
        $event = $this->createEvent();
        /** @var TemplatedEmail $email */
        $email = $this->factory()->eventGuestSignupEmail($this->createUser(), $event, 'https://example.test/login');

        self::assertSame($event, $email->getContext()['event']);
    }

    // --- resetPassword ---

    public function testResetPasswordEmailHasSubject(): void
    {
        $email = $this->factory()->resetPassword($this->createUser(), 'token123');

        self::assertSame('Durf - Reset your password', $email->getSubject());
    }

    public function testResetPasswordEmailUsesCorrectTemplate(): void
    {
        /** @var TemplatedEmail $email */
        $email = $this->factory()->resetPassword($this->createUser(), 'token123');

        self::assertSame('email/user/reset.password.html.twig', $email->getHtmlTemplate());
    }

    public function testResetPasswordEmailContextContainsToken(): void
    {
        /** @var TemplatedEmail $email */
        $email = $this->factory()->resetPassword($this->createUser(), 'my-reset-token');

        self::assertSame('my-reset-token', $email->getContext()['resetToken']);
    }

    // --- userEnabled ---

    public function testUserEnabledEmailHasSubject(): void
    {
        $email = $this->factory()->userEnabled($this->createUser());

        self::assertSame('Durf - Account activated', $email->getSubject());
    }

    public function testUserEnabledEmailUsesCorrectTemplate(): void
    {
        /** @var TemplatedEmail $email */
        $email = $this->factory()->userEnabled($this->createUser());

        self::assertSame('email/user/enabled.html.twig', $email->getHtmlTemplate());
    }

    // --- newUserNotification ---

    public function testNewUserNotificationHasNoRecipient(): void
    {
        $email = $this->factory()->newUserNotification($this->createUser());

        self::assertEmpty($email->getTo());
    }

    public function testNewUserNotificationSubjectContainsUserName(): void
    {
        $user = $this->createUser();
        $email = $this->factory()->newUserNotification($user);

        self::assertStringContainsString('Test User', $email->getSubject());
    }

    public function testNewUserNotificationUsesCorrectTemplate(): void
    {
        /** @var TemplatedEmail $email */
        $email = $this->factory()->newUserNotification($this->createUser());

        self::assertSame('email/user/new_user_notification.html.twig', $email->getHtmlTemplate());
    }

    public function testNewUserNotificationContextContainsUser(): void
    {
        $user = $this->createUser();
        /** @var TemplatedEmail $email */
        $email = $this->factory()->newUserNotification($user);

        self::assertSame($user, $email->getContext()['newUser']);
        // The committee is not addressed as the new user
        self::assertNull($email->getContext()['user']);
    }

    // --- subscription emails ---

    private function createSubscription(): EventSubscription
    {
        $subscription = new EventSubscription();
        $subscription->amount = 1;
        $subscription->createdUser = $this->createUser('member@example.com');
        $subscription->event = $this->createEvent();

        return $subscription;
    }

    public function testSubscriptionConfirmedAttachesTheCalendarFile(): void
    {
        $email = $this->factory()->subscriptionConfirmed($this->createSubscription(), "BEGIN:VCALENDAR\r\nEND:VCALENDAR");

        self::assertSame('member@example.com', $email->getTo()[0]->getAddress());
        self::assertCount(1, $email->getAttachments());
        self::assertSame('event.ics', $email->getAttachments()[0]->getFilename());
        self::assertStringStartsWith('text/calendar', $email->getAttachments()[0]->getContentType());
    }

    public function testEventReminderUsesTheReminderTemplate(): void
    {
        $email = $this->factory()->eventReminder($this->createSubscription());

        self::assertStringStartsWith('Durf - Reminder: ', $email->getSubject());
        self::assertSame('email/event/reminder.html.twig', $email->getHtmlTemplate());
    }

    // --- account security emails ---

    public function testConfirmEmailChangeGoesToTheNewAddress(): void
    {
        $email = $this->factory()->confirmEmailChange($this->createUser('old@example.com'), 'new@example.com', 'https://example.test/confirm');

        self::assertCount(1, $email->getTo());
        self::assertSame('new@example.com', $email->getTo()[0]->getAddress());
        self::assertSame('https://example.test/confirm', $email->getContext()['confirmUrl']);
    }

    public function testEmailChangedGoesToTheOldAddress(): void
    {
        $user = $this->createUser('new@example.com');

        $email = $this->factory()->emailChanged($user, 'old@example.com');

        self::assertCount(1, $email->getTo());
        self::assertSame('old@example.com', $email->getTo()[0]->getAddress());
    }

    // --- locale ---

    public function testEmailIsInTheLanguageOfTheRecipient(): void
    {
        $user = $this->createUser();
        $user->locale = 'nl';

        $email = $this->factory()->signupEmail($user);

        self::assertSame('nl', $email->getLocale());
        self::assertSame(['nl'], $this->translatedIn);
    }

    public function testEmailFallsBackToTheDefaultLanguage(): void
    {
        $email = $this->factory()->signupEmail($this->createUser());

        self::assertSame('en', $email->getLocale());
        self::assertSame(['en'], $this->translatedIn);
    }

    public function testSubjectHasTheEventName(): void
    {
        $email = $this->factory()->eventReminder($this->createSubscription());

        self::assertSame('Durf - Reminder: Test Event', $email->getSubject());
    }

    public function testNewUserNotificationIsInTheDefaultLanguage(): void
    {
        $user = $this->createUser();
        $user->locale = 'nl';

        $email = $this->factory()->newUserNotification($user);

        self::assertSame('en', $email->getLocale());
        self::assertSame('Durf - New user registered: Test User', $email->getSubject());
    }
}
