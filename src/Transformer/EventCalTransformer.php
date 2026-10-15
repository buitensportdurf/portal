<?php

namespace App\Transformer;

use App\Entity\Event\Event;
use App\Entity\Event\EventSubscription;
use Eluceo\iCal\Domain\Entity\Attendee;
use Eluceo\iCal\Domain\Entity\Event as IcalEvent;
use Eluceo\iCal\Domain\Enum\CalendarUserType;
use Eluceo\iCal\Domain\Enum\ParticipationStatus;
use Eluceo\iCal\Domain\Enum\EventStatus;
use Eluceo\iCal\Domain\ValueObject\DateTime;
use Eluceo\iCal\Domain\ValueObject\EmailAddress;
use Eluceo\iCal\Domain\ValueObject\Location;
use Eluceo\iCal\Domain\ValueObject\TimeSpan;
use Eluceo\iCal\Domain\ValueObject\UniqueIdentifier;
use Eluceo\iCal\Domain\ValueObject\Uri;
use League\CommonMark\CommonMarkConverter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class EventCalTransformer
{
    private CommonMarkConverter $markdown;

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        #[Autowire('%kernel.secret%')]
        private string $secret,
    ) {
        $this->markdown = new CommonMarkConverter(['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    /**
     * $withAttendees lists the subscribers, for feeds that only their own subscriber can open.
     */
    public function transform(Event $event, bool $withAttendees = false): IcalEvent
    {
        $url = $this->urlGenerator->generate('event_event_show', ['id' => $event->id], UrlGeneratorInterface::ABSOLUTE_URL);

        // Always with times and a TZID: calendar apps show multi-day events with times fine, and
        // without a TZID they would use the device's timezone
        $icalEvent = new IcalEvent(new UniqueIdentifier('portal.buitensportdurf.nl/event/' . $event->id));
        $icalEvent
            ->setOccurrence(new TimeSpan(
                new DateTime($event->startDate, true),
                new DateTime($event->endDate, true),
            ))
            ->setUrl(new Uri($url))
            ->setSummary($event->isCancelled()
                ? $this->translator->trans('Cancelled: {name}', ['name' => $event->name])
                : $event->name)
            ->setDescription(trim($this->plainText($event->description) . "\n\n" . $url))
            ->setLocation(new Location($event->location))
        ;
        if ($event->isCancelled()) {
            $icalEvent->setStatus(EventStatus::CANCELLED());
        }
        if ($withAttendees) {
            $icalEvent->setAttendees($this->attendees($event));
        }

        return $icalEvent;
    }

    /**
     * Subscribers by the name the website shows, guests numbered. Calendars need an email address per
     * attendee, so each gets a made-up one: stable per person, not traceable to them and on a
     * reserved domain that never receives mail.
     *
     * @return list<Attendee>
     */
    private function attendees(Event $event): array
    {
        $attendees = [];
        $guests = 0;
        foreach ($event->getEventSubscriptions() as $subscription) {
            $user = $subscription->createdUser;
            $name = $user->guest
                ? $this->translator->trans('Guest {number}', ['number' => ++$guests])
                : (string) $user;
            $attendees[] = new Attendee(new EmailAddress($this->anonymousAddress($subscription)))
                ->setDisplayName($subscription->amount > 1
                    ? $this->translator->trans('{name} (+{extra})', ['name' => $name, 'extra' => $subscription->amount - 1])
                    : $name)
                ->setCalendarUserType(CalendarUserType::INDIVIDUAL())
                // No setResponseNeededFromAttendee(false): eluceo/ical then writes RSVP=TRUE, and
                // leaving RSVP out means false
                ->setParticipationStatus(ParticipationStatus::ACCEPTED())
            ;
        }

        return $attendees;
    }

    private function anonymousAddress(EventSubscription $subscription): string
    {
        // Not the user id itself: that is the secret in their personal feed URL
        return substr(hash_hmac('sha256', (string) $subscription->createdUser->id, $this->secret), 0, 16) . '@participant.invalid';
    }

    /**
     * Calendar apps show descriptions as plain text, so render the Markdown and keep link targets.
     */
    private function plainText(string $markdown): string
    {
        $html = (string) $this->markdown->convert($markdown);
        $html = preg_replace_callback(
            '#<a [^>]*href="([^"]*)"[^>]*>(.*?)</a>#s',
            function (array $match): string {
                $text = strip_tags($match[2]);

                return $text === $match[1] ? $text : $text . ' (' . $match[1] . ')';
            },
            $html,
        );
        $html = str_replace('<li>', '<li>• ', $html);
        // A blank line between paragraphs, lists and headings, as in the Markdown
        $html = preg_replace('#</(p|ul|ol|h[1-6]|blockquote|pre)>#', "\$0\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);

        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }
}
