<?php

namespace App\Entity;

use Symfony\Component\Translation\TranslatableMessage;

use function Symfony\Component\Translation\t;

/**
 * Emails about events someone subscribed to, which they can switch off. Account and security
 * emails are not in here: those always go out.
 */
enum EmailNotification: string
{
    case SubscriptionConfirmed = 'subscription_confirmed';
    case Reminder = 'reminder';
    case EventChanged = 'event_changed';

    public function label(): TranslatableMessage
    {
        return match ($this) {
            self::SubscriptionConfirmed => t('Confirmation when I subscribe, with a calendar file'),
            self::Reminder => t('Reminder two days before an event'),
            self::EventChanged => t('Changes to the date, time or location, and cancellations'),
        };
    }
}
