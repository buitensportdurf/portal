<?php

namespace App\Service;

use App\Entity\Event\Event;
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

    public static function eventGuestSignupEmail(User $user, Event $event): Email
    {
        return self::createEmailBase($user, 'Guest account created', ['event' => $event])
                   ->htmlTemplate('email/user/signup.guest.html.twig')
        ;
    }

    public static function resetPassword(User $user, string $resetToken): Email
    {
        return self::createEmailBase($user, 'Password reset request', ['resetToken' => $resetToken])
                   ->htmlTemplate('email/user/reset.password.html.twig')
        ;
    }

    public static function userEnabled(User $user): Email
    {
        return self::createEmailBase($user, 'Account enabled')
                   ->htmlTemplate('email/user/enabled.html.twig')
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
            ->context(['user' => $user, 'title' => $title])
            ->htmlTemplate('email/user/new_user_notification.html.twig')
        ;
    }
}