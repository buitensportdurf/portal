<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Email;

class EmailFactory
{
    public static function signupEmail(
        User $user,
    ): Email
    {
        return (new TemplatedEmail())
            ->to($user->getEmail())
            ->subject('Buitensport Vereniging Durf - Account created')
            ->htmlTemplate('email/signup.html.twig')
            ->context(['user' => $user]);
    }
}