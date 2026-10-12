<?php

namespace App\Service;

use App\Entity\Event\Event;
use App\Entity\Event\EventSubscription;
use App\Entity\User;
use App\Recurrence\Plan\FieldChange;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

/**
 * Builds the emails, in the language of the recipient: the subject here, the body when it is rendered.
 */
readonly class EmailFactory
{
    public function __construct(
        private TranslatorInterface $translator,
        #[Autowire(param: 'kernel.default_locale')]
        private string $defaultLocale = 'en',
    ) {}

    private function createEmailBase(User $user, TranslatableInterface $title, array $context = []): TemplatedEmail
    {
        $locale = $user->locale ?? $this->defaultLocale;
        $title = 'Durf - ' . $title->trans($this->translator, $locale);

        return new TemplatedEmail()
            ->to($user->email)
            ->subject($title)
            ->locale($locale)
            ->context(['user' => $user, 'title' => $title] + $context)
        ;
    }

    public function signupEmail(User $user): Email
    {
        return $this->createEmailBase($user, t('Account created'))
                    ->htmlTemplate('email/user/signup.html.twig')
        ;
    }

    public function eventGuestSignupEmail(User $user, Event $event, string $loginUrl): Email
    {
        return $this->createEmailBase($user, t('Subscribe to {event}', ['event' => $event->name]), ['event' => $event, 'loginUrl' => $loginUrl])
                    ->htmlTemplate('email/user/signup.guest.html.twig')
        ;
    }

    public function guestUpgrade(User $user, string $loginUrl): Email
    {
        return $this->createEmailBase($user, t('Finish your registration'), ['loginUrl' => $loginUrl])
                    ->htmlTemplate('email/user/guest_upgrade.html.twig')
        ;
    }

    public function accountExists(User $user): Email
    {
        return $this->createEmailBase($user, t('You already have an account'))
                    ->htmlTemplate('email/user/account_exists.html.twig')
        ;
    }

    public function resetPassword(User $user, string $resetToken): Email
    {
        return $this->createEmailBase($user, t('Reset your password'), ['resetToken' => $resetToken])
                    ->htmlTemplate('email/user/reset.password.html.twig')
        ;
    }

    public function passwordChanged(User $user): Email
    {
        return $this->createEmailBase($user, t('Your password was changed'))
                    ->htmlTemplate('email/user/password_changed.html.twig')
        ;
    }

    /**
     * Sent to the new address, which has to be confirmed before it is used.
     */
    public function confirmEmailChange(User $user, string $newEmail, string $confirmUrl): Email
    {
        return $this->createEmailBase($user, t('Confirm your new email address'), ['newEmail' => $newEmail, 'confirmUrl' => $confirmUrl])
                    ->to($newEmail)
                    ->htmlTemplate('email/user/confirm_email_change.html.twig')
        ;
    }

    /**
     * Sent to the old address once the change went through.
     */
    public function emailChanged(User $user, string $oldEmail): Email
    {
        return $this->createEmailBase($user, t('Your email address was changed'))
                    ->to($oldEmail)
                    ->htmlTemplate('email/user/email_changed.html.twig')
        ;
    }

    public function userEnabled(User $user): Email
    {
        return $this->createEmailBase($user, t('Account activated'))
                    ->htmlTemplate('email/user/enabled.html.twig')
        ;
    }

    public function subscriptionConfirmed(EventSubscription $subscription, string $ics): Email
    {
        $event = $subscription->event;

        return $this->createEmailBase($subscription->createdUser, t('Subscribed to {event}', ['event' => $event->name]), ['event' => $event, 'subscription' => $subscription])
                    ->htmlTemplate('email/event/subscribed.html.twig')
                    ->attach($ics, 'event.ics', 'text/calendar; charset=utf-8; method=PUBLISH')
        ;
    }

    public function eventReminder(EventSubscription $subscription): Email
    {
        $event = $subscription->event;

        return $this->createEmailBase($subscription->createdUser, t('Reminder: {event}', ['event' => $event->name]), ['event' => $event, 'subscription' => $subscription])
                    ->htmlTemplate('email/event/reminder.html.twig')
        ;
    }

    /**
     * @param list<FieldChange> $changes
     */
    public function eventChanged(User $user, Event $event, array $changes): Email
    {
        return $this->createEmailBase($user, t('Event changed: {event}', ['event' => $event->name]), ['event' => $event, 'changes' => $changes])
                    ->htmlTemplate('email/event/changed.html.twig')
        ;
    }

    public function eventCancelled(User $user, Event $event): Email
    {
        return $this->createEmailBase($user, t('Event cancelled: {event}', ['event' => $event->name]), ['event' => $event])
                    ->htmlTemplate('email/event/cancelled.html.twig')
        ;
    }

    /**
     * Goes to the committee's shared address, so in the default language.
     */
    public function newUserNotification(User $user): Email
    {
        $title = 'Durf - ' . t('New user registered: {name}', ['name' => $user->name])->trans($this->translator, $this->defaultLocale);

        return new TemplatedEmail()
            ->subject($title)
            ->locale($this->defaultLocale)
            ->context(['user' => null, 'newUser' => $user, 'title' => $title])
            ->htmlTemplate('email/user/new_user_notification.html.twig')
        ;
    }
}
