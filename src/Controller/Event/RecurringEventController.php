<?php

namespace App\Controller\Event;

use App\Entity\Event\RecurringEvent;
use App\Form\ConfirmationType;
use App\Form\Event\RecurringEventType;
use App\Repository\Event\RecurringEventRepository;
use DateInterval;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/event/recurring_event', name: 'event_recurring_event')]
class RecurringEventController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/index', name: '_index')]
    public function index(RecurringEventRepository $eventRepository): Response
    {
        return $this->render('event/recurring_event/index.html.twig', [
            'recurringEvents' => $eventRepository->findAll(),
        ]);
    }

    #[Route('/new', name: '_new')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $recurringEvent = new RecurringEvent();
        $recurringEvent->setDuration(new DateInterval('PT0S'));
        $form = $this->createForm(RecurringEventType::class, $recurringEvent);
        $form->add('Save', SubmitType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($recurringEvent);
            $entityManager->flush();

            return $this->redirectToRoute('event_recurring_event_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('event/recurring_event/new.html.twig', [
            'recurringEvent' => $recurringEvent,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: '_edit')]
    public function edit(
        Request        $request,
        RecurringEvent $recurringEvent,
    ): Response
    {
        $form = $this->createForm(RecurringEventType::class, $recurringEvent);
        $form->add('Save', SubmitType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->deleteFutureEvents($recurringEvent);
            $newEventCount = $this->createNewEvents($recurringEvent);
            $this->em->flush();

            $this->addFlash('success', sprintf('Updated event "%s" and created %d new events', $recurringEvent, $newEventCount));
            return $this->redirectToRoute('event_recurring_event_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('event/recurring_event/edit.html.twig', [
            'recurringEvent' => $recurringEvent,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/show', name: '_show')]
    public function show(RecurringEvent $recurringEvent): Response
    {
        return $this->render('event/recurring_event/show.html.twig', [
            'recurringEvent' => $recurringEvent,
        ]);
    }

    #[Route('/{id}/delete', name: '_delete')]
    public function delete(Request $request, RecurringEvent $recurringEvent, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ConfirmationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->deleteFutureEvents($recurringEvent);
            $entityManager->remove($recurringEvent);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Deleted event "%s"', $recurringEvent));
            return $this->redirectToRoute('event_recurring_event_index');
        }

        return $this->render('general/confirmation.form.html.twig', [
            'message' => sprintf('Are you sure you want to delete event "%s"', $recurringEvent),
            'form' => $form->createView(),
        ]);
    }

    private function deleteFutureEvents(RecurringEvent $recurringEvent): void
    {
        foreach ($recurringEvent->getFutureEvents() as $event) {
            $this->em->remove($event);
            $recurringEvent->removeEvent($event);
        }
    }

    private function createNewEvents(RecurringEvent $recurringEvent): int
    {
        $newEventCount = 0;
        do {
            $event = $recurringEvent->createNextEvent();
            $this->em->persist($event);
            $newEventCount++;
        } while ($event->getStartDate() < new \DateTimeImmutable('+1 year'));
        return $newEventCount;
    }
}
