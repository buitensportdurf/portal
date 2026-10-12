<?php

namespace App\Controller\Event;

use App\Entity\Event\Event;
use App\Entity\Event\Tag;
use App\Entity\User;
use App\Repository\Event\EventRepository;
use App\Repository\Event\TagRepository;
use App\Service\CalendarBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/event/calendar', name: 'event_calendar')]
class CalendarController extends AbstractController
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly CalendarBuilder $calendarBuilder,
        private readonly TranslatorInterface $translator,
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
        return $this->getCalendarResponse($this->eventRepository->findForCalendar(), 'Durf - ' . $this->translator->trans('All events'));
    }

    /**
     * Calendar apps send no useful language, so the personal feed uses the account's.
     */
    #[Route('/user/{user}/cal.ics', name: '_user')]
    public function user(User $user, LocaleSwitcher $localeSwitcher): Response
    {
        return $localeSwitcher->runWithLocale(
            $user->locale ?? $localeSwitcher->getLocale(),
            fn() => $this->getCalendarResponse($this->eventRepository->findForCalendar(user: $user), 'Durf - ' . $this->translator->trans('My events')),
        );
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
        return new Response($this->calendarBuilder->build($events, $name), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="cal.ics"',
        ]);
    }
}
