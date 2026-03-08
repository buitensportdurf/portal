<?php

namespace App\Tests\Service;

use App\Service\CommissionMailer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class CommissionMailerTest extends TestCase
{
    public function testSendDispatchesEmailWhenConfigured(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send');

        $commissionMailer = new CommissionMailer($mailer, 'commission@example.com');
        $email = new Email();
        $email->subject('Test');

        $commissionMailer->send($email);
    }

    public function testSendSetsRecipientFromConfig(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email): bool {
                $to = $email->getTo();
                return count($to) === 1 && $to[0]->getAddress() === 'activiteiten@example.com';
            }));

        $commissionMailer = new CommissionMailer($mailer, 'activiteiten@example.com');
        $commissionMailer->send(new Email());
    }

    public function testSendSkipsWhenEmailEmpty(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $commissionMailer = new CommissionMailer($mailer, '');
        $commissionMailer->send(new Email());
    }

    public function testSendSkipsWhenEmailDefaultEmpty(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $commissionMailer = new CommissionMailer($mailer);
        $commissionMailer->send(new Email());
    }
}
