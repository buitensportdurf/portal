<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

readonly class CommissionMailer
{
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire(env: 'COMMISSION_EMAIL')]
        private string $commissionEmail = '',
    ) {}

    public function send(Email $email): void
    {
        if ($this->commissionEmail === '') {
            return;
        }

        $email->to($this->commissionEmail);
        $this->mailer->send($email);
    }
}
