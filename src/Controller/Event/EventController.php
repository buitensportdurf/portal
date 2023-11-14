<?php

namespace App\Controller\Event;

use App\Entity\Event\Event;
use App\Form\ConfirmationType;
use App\Form\Event\EventType;
use App\Repository\Event\EventRepository;
use App\Transformer\EventCalTransformer;
use Doctrine\ORM\EntityManagerInterface;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/event/event', name: 'event_event')]
class EventController extends AbstractController
{
    #[Route('/index', name: '_index')]
    public function index(EventRepository $eventRepository): Response
    {
        return $this->render('event/event/index.html.twig', [
            'events' => $eventRepository->findAll(),
        ]);
    }

    #[Route('/new', name: '_new')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $event = new Event();
        $form = $this->createForm(EventType::class, $event);
        $form->add('Save', SubmitType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($event);
            $entityManager->flush();

            return $this->redirectToRoute('event_event_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('event/event/new.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }

    #[Route('/show/{id}', name: '_show')]
    public function show(Event $event): Response
    {
        return $this->render('event/event/show.html.twig', [
            'event' => $event,
        ]);
    }

    #[Route('/edit/{id}', name: '_edit')]
    public function edit(Request $request, Event $event, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(EventType::class, $event);
        $form->add('Save', SubmitType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('event_event_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('event/event/edit.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: '_delete')]
    public function delete(Request $request, Event $event, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ConfirmationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->remove($event);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Deleted event "%s"', $event));
            return $this->redirectToRoute('event_event_index');
        }

        return $this->render('general/confirmation.form.html.twig', [
            'message' => sprintf('Are you sure you want to delete event "%s"', $event),
            'form' => $form->createView(),
        ]);
    }

    #[Route('/cal.ics', name: '_cal')]
    public function cal(EventCalTransformer $transformer, EventRepository $eventRepository): Response
    {
        $calendar = new Calendar();
        foreach ($eventRepository->findAll() as $event) {
            $calendar->addEvent($transformer($event));
        }

        $componentFactory = new CalendarFactory();
        $calendarComponent = $componentFactory->createCalendar($calendar);

        return new Response($calendarComponent, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="cal.ics"',
        ]);
    }
}
