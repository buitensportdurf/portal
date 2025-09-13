<?php

namespace App\Service;

use App\Entity\Event\Event;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Email;

class EmailFactory
{
    private static function createEmailBase(User $user, string $title, array $context = []): TemplatedEmail
    {
        $title = 'Durf - ' . $title;
        return new TemplatedEmail()
            ->to($user->getEmail())
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
}