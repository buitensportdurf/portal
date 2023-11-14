<?php

namespace App\Controller\Event;


use App\Entity\Event\Event;
use App\Repository\Event\EventRepository;
use App\Repository\Event\TagRepository;
use App\Transformer\EventCalTransformer;
use DateInterval;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/event/calendar', name: 'event_calendar')]
class CalendarController extends AbstractController
{
    public function __construct(
        private readonly EventRepository     $eventRepository,
        private readonly EventCalTransformer $eventCalTransformer,
    )
    {
    }

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
        return $this->getCalendarResponse($this->eventRepository->findAll());
    }

    #[Route('/tag/{tag}/cal.ics', name: '_tag')]
    public function tag(string $tag): Response
    {
        return $this->getCalendarResponse($this->eventRepository->findEventsByTag($tag));
    }

    /**
     * @param array<Event> $events
     * @return Response
     */
    private function getCalendarResponse(array $events): Response
    {
        $calendar = new Calendar();
        foreach ($events as $event) {
            $calendar->addEvent($this->eventCalTransformer->transform($event));
        }
        $calendar->setProductIdentifier('buitensport_durf_events')
            ->setPublishedTTL(new DateInterval('PT1H'));

        $componentFactory = new CalendarFactory();
        $calendarComponent = $componentFactory->createCalendar($calendar);

        return new Response($calendarComponent, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="cal.ics"',
        ]);
    }
}