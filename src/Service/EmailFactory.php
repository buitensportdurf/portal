<?php

namespace App\Service;

use App\Entity\Event\Event;
use App\Entity\Event\EventSubscription;
use App\Entity\User;
use App\Recurrence\Plan\FieldChange;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Email;

class EmailFactory
{
    private static function createEmailBase(User $user, string $title, array $context = []): TemplatedEmail
    {
        $title = 'Durf - ' . $title;
        return new TemplatedEmail()
            ->to($user->email)
            ->subject($title)
            ->context(['user' => $user, 'title' => $title] + $context)
        ;
    }

    public static function signupEmail(User $user): Email
    {
        return self::createEmailBase($user, 'Account created')
                   ->htmlTemplate('email/user/signup.html.twig')
        ;
    }

    public static function eventGuestSignupEmail(User $user, Event $event, string $loginUrl): Email
    {
        return self::createEmailBase($user, 'Subscribe to ' . $event->name, ['event' => $event, 'loginUrl' => $loginUrl])
                   ->htmlTemplate('email/user/signup.guest.html.twig')
        ;
    }

    public static function guestUpgrade(User $user, string $loginUrl): Email
    {
        return self::createEmailBase($user, 'Finish your registration', ['loginUrl' => $loginUrl])
                   ->htmlTemplate('email/user/guest_upgrade.html.twig')
        ;
    }

    public static function accountExists(User $user): Email
    {
        return self::createEmailBase($user, 'You already have an account')
                   ->htmlTemplate('email/user/account_exists.html.twig')
        ;
    }

    public static function resetPassword(User $user, string $resetToken): Email
    {
        return self::createEmailBase($user, 'Reset your password', ['resetToken' => $resetToken])
                   ->htmlTemplate('email/user/reset.password.html.twig')
        ;
    }

    public static function passwordChanged(User $user): Email
    {
        return self::createEmailBase($user, 'Your password was changed')
                   ->htmlTemplate('email/user/password_changed.html.twig')
        ;
    }

    /**
     * Sent to the new address, which has to be confirmed before it is used.
     */
    public static function confirmEmailChange(User $user, string $newEmail, string $confirmUrl): Email
    {
        return self::createEmailBase($user, 'Confirm your new email address', ['newEmail' => $newEmail, 'confirmUrl' => $confirmUrl])
                   ->to($newEmail)
                   ->htmlTemplate('email/user/confirm_email_change.html.twig')
        ;
    }

    /**
     * Sent to the old address once the change went through.
     */
    public static function emailChanged(User $user, string $oldEmail): Email
    {
        return self::createEmailBase($user, 'Your email address was changed')
                   ->to($oldEmail)
                   ->htmlTemplate('email/user/email_changed.html.twig')
        ;
    }

    public static function userEnabled(User $user): Email
    {
        return self::createEmailBase($user, 'Account activated')
                   ->htmlTemplate('email/user/enabled.html.twig')
        ;
    }

    public static function subscriptionConfirmed(EventSubscription $subscription, string $ics): Email
    {
        $event = $subscription->event;

        return self::createEmailBase($subscription->createdUser, 'Subscribed to ' . $event->name, ['event' => $event, 'subscription' => $subscription])
                   ->htmlTemplate('email/event/subscribed.html.twig')
                   ->attach($ics, 'event.ics', 'text/calendar; charset=utf-8; method=PUBLISH')
        ;
    }

    public static function eventReminder(EventSubscription $subscription): Email
    {
        $event = $subscription->event;

        return self::createEmailBase($subscription->createdUser, 'Reminder: ' . $event->name, ['event' => $event, 'subscription' => $subscription])
                   ->htmlTemplate('email/event/reminder.html.twig')
        ;
    }

    /**
     * @param list<FieldChange> $changes
     */
    public static function eventChanged(User $user, Event $event, array $changes): Email
    {
        return self::createEmailBase($user, 'Event changed: ' . $event->name, ['event' => $event, 'changes' => $changes])
                   ->htmlTemplate('email/event/changed.html.twig')
        ;
    }

    public static function eventCancelled(User $user, Event $event): Email
    {
        return self::createEmailBase($user, 'Event cancelled: ' . $event->name, ['event' => $event])
                   ->htmlTemplate('email/event/cancelled.html.twig')
        ;
    }

    public static function newUserNotification(User $user): Email
    {
        $title = 'Durf - New user registered: ' . $user->name;
        return (new TemplatedEmail())
            ->subject($title)
            ->context(['user' => null, 'newUser' => $user, 'title' => $title])
            ->htmlTemplate('email/user/new_user_notification.html.twig')
        ;
    }
}
