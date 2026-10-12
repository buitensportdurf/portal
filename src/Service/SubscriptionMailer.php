<?php

namespace App\Service;

use App\Entity\EmailNotification;
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
        private EmailFactory $emailFactory,
        private LoggerInterface $logger,
    ) {}

    public function sendConfirmation(EventSubscription $subscription): void
    {
        if (!$this->wants($subscription, EmailNotification::SubscriptionConfirmed)) {
            return;
        }
        $ics = $this->calendarBuilder->build([$subscription->event], 'Durf - ' . $subscription->event->name);
        $this->send($this->emailFactory->subscriptionConfirmed($subscription, $ics), $subscription);
    }

    public function wants(EventSubscription $subscription, EmailNotification $notification): bool
    {
        $user = $subscription->createdUser;

        return $user?->email !== null && $user->wantsEmail($notification);
    }

    public function sendReminder(EventSubscription $subscription): void
    {
        if (!$this->wants($subscription, EmailNotification::Reminder)) {
            return;
        }
        $this->send($this->emailFactory->eventReminder($subscription), $subscription);
    }

    private function send(Email $email, EventSubscription $subscription): void
    {
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
