<?php

namespace App\Controller\Event;

use App\Entity\Event\Event;
use App\Entity\Event\Tag;
use App\Entity\User;
use App\Repository\Event\EventRepository;
use App\Repository\Event\TagRepository;
use App\Transformer\EventCalTransformer;
use DateInterval;
use DateTimeImmutable;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Domain\Entity\TimeZone;
use Eluceo\iCal\Presentation\Component\Property;
use Eluceo\iCal\Presentation\Component\Property\Value\TextValue;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/event/calendar', name: 'event_calendar')]
class CalendarController extends AbstractController
{
    private const string TIMEZONE = 'Europe/Amsterdam';

    public function __construct(
        private readonly EventRepository     $eventRepository,
        private readonly EventCalTransformer $eventCalTransformer,
    ) {}

    #[Route('/index', name: '_index')]
    public function index(TagRepository $tagRepository): Response
    {
        return $this->render('event/calendar/index.html.twig', [
            'tags' => $tagRepository->findAll(),
        ]);
    }

    #[Route('/all/cal.ics', name: '_all')]
    public function cal(): Response
    {
        return $this->getCalendarResponse($this->eventRepository->findForCalendar(), 'Durf - All events');
    }

    #[Route('/user/{user}/cal.ics', name: '_user')]
    public function user(User $user): Response
    {
        return $this->getCalendarResponse($this->eventRepository->findForCalendar(user: $user), 'Durf - My events');
    }

    #[Route('/tag/{tag}/cal.ics', name: '_tag')]
    public function tag(Tag $tag): Response
    {
        return $this->getCalendarResponse($this->eventRepository->findForCalendar($tag), 'Durf - ' . $tag);
    }

    /**
     * @param array<Event> $events
     */
    private function getCalendarResponse(array $events, string $name): Response
    {
        $calendar = new Calendar(array_map($this->eventCalTransformer->transform(...), $events));

        // Event times refer to this timezone, so its definition has to cover every event in the feed
        $first = new DateTimeImmutable();
        $last = $first;
        foreach ($events as $event) {
            $first = min($first, $event->startDate);
            $last = max($last, $event->endDate);
        }
        $calendar->addTimeZone(TimeZone::createFromPhpDateTimeZone(
            new \DateTimeZone(self::TIMEZONE),
            $first->sub(new DateInterval('P1D')),
            $last->add(new DateInterval('P1D')),
        ));
        $calendar
            ->setProductIdentifier('-//Buitensport Durf//Portal//EN')
            ->setPublishedTTL(new DateInterval('PT1H'))
        ;

        $component = new CalendarFactory()->createCalendar($calendar)
            ->withProperty(new Property('X-WR-CALNAME', new TextValue($name)))
            ->withProperty(new Property('X-WR-TIMEZONE', new TextValue(self::TIMEZONE)))
        ;

        return new Response((string) $component, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="cal.ics"',
        ]);
    }
}
