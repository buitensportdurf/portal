<?php

namespace App\Service;

use App\Entity\Event\EventSubscription;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Emails about a subscription. A failing mail server is logged instead of turning a saved
 * subscription into an error page.
 */
readonly class SubscriptionMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private CalendarBuilder $calendarBuilder,
        private LoggerInterface $logger,
    ) {}

    public function sendConfirmation(EventSubscription $subscription): void
    {
        $ics = $this->calendarBuilder->build([$subscription->event], 'Durf - ' . $subscription->event->name);
        $this->send(EmailFactory::subscriptionConfirmed($subscription, $ics), $subscription);
    }

    public function sendReminder(EventSubscription $subscription): void
    {
        $this->send(EmailFactory::eventReminder($subscription), $subscription);
    }

    private function send(Email $email, EventSubscription $subscription): void
    {
        if (!$subscription->createdUser?->email) {
            return;
        }
        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Could not send subscription email', [
                'subscription' => $subscription->id,
                'exception' => $e,
            ]);
        }
    }
}
