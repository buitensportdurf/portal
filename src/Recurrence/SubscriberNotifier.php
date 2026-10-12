<?php

namespace App\Recurrence;

use App\Recurrence\Plan\ChangeType;
use App\Recurrence\Plan\OccurrenceChange;
use App\Service\EmailFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Tells subscribers their event moved, changed location or was cancelled.
 */
readonly class SubscriberNotifier
{
    public function __construct(
        private MailerInterface $mailer,
        private EmailFactory $emailFactory,
        private LoggerInterface $logger,
    ) {}

    public function notify(OccurrenceChange $change): void
    {
        $event = $change->event;
        $changes = array_values(array_filter(
            $change->changes,
            fn($fieldChange) => $fieldChange->field->concernsSubscribers(),
        ));

        foreach ($change->recipients() as $user) {
            $email = $change->type === ChangeType::Cancel
                ? $this->emailFactory->eventCancelled($user, $event)
                : $this->emailFactory->eventChanged($user, $event, $changes);
            try {
                $this->mailer->send($email);
            } catch (TransportExceptionInterface $e) {
                // One unreachable subscriber should not undo a series edit that is already saved
                $this->logger->error('Could not notify subscriber about event change', [
                    'event' => $event->id,
                    'user' => $user->email,
                    'exception' => $e,
                ]);
            }
        }
    }
}
