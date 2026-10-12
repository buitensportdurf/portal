<?php

namespace App\Entity;

/**
 * Emails about events someone subscribed to, which they can switch off. Account and security
 * emails are not in here: those always go out.
 */
enum EmailNotification: string
{
    case SubscriptionConfirmed = 'subscription_confirmed';
    case Reminder = 'reminder';
    case EventChanged = 'event_changed';

    public function label(): string
    {
        return match ($this) {
            self::SubscriptionConfirmed => 'Confirmation when I subscribe, with a calendar file',
            self::Reminder => 'Reminder two days before an event',
            self::EventChanged => 'Changes to the date, time or location, and cancellations',
        };
    }
}
