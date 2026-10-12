<?php

namespace App\Transformer;

use App\Entity\Event\Event;
use Eluceo\iCal\Domain\Entity\Event as IcalEvent;
use Eluceo\iCal\Domain\Enum\EventStatus;
use Eluceo\iCal\Domain\ValueObject\DateTime;
use Eluceo\iCal\Domain\ValueObject\Location;
use Eluceo\iCal\Domain\ValueObject\TimeSpan;
use Eluceo\iCal\Domain\ValueObject\UniqueIdentifier;
use Eluceo\iCal\Domain\ValueObject\Uri;
use League\CommonMark\CommonMarkConverter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class EventCalTransformer
{
    private CommonMarkConverter $markdown;

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
    ) {
        $this->markdown = new CommonMarkConverter(['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    public function transform(Event $event): IcalEvent
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

        return $icalEvent;
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
