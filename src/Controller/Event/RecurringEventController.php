<?php

namespace App\Controller\Event;

use App\Entity\Event\RecurringEvent;
use App\Form\ConfirmationType;
use App\Form\Event\RecurringEventType;
use App\Repository\Event\EventRepository;
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
        private readonly EntityManagerInterface   $em,
        private readonly RecurringEventRepository $repository,
        private readonly EventRepository          $eventRepository,
    ) {}

    #[Route('/index', name: '_index')]
    public function index(): Response
    {
        return $this->render('event/recurring_event/index.html.twig', [
            'recurringEvents' => $this->repository->findAll(),
        ]);
    }

    #[Route('/new', name: '_new')]
    public function new(Request $request): Response
    {
        $recurringEvent = new RecurringEvent();
        $recurringEvent->setDuration(new DateInterval('PT0S'));
        $form = $this->createForm(RecurringEventType::class, $recurringEvent);
        $form->add('Save', SubmitType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($recurringEvent);
            $this->em->flush();

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
        $currentRecurrenceRule = $recurringEvent->getRecurrenceRule();
        $form = $this->createForm(RecurringEventType::class, $recurringEvent);
        $form->add('Save', SubmitType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newRecurrenceRule = $recurringEvent->getRecurrenceRule();
            if ($currentRecurrenceRule !== $newRecurrenceRule) {
                $oldEventCount = $this->deleteFutureEvents($recurringEvent);
                $newEventCount = $this->createNewEvents($recurringEvent);
                $this->addFlash('success', sprintf('Recurrence rule changed, deleted %d old events and created %d new events', $oldEventCount, $newEventCount));
            } else {
                foreach ($recurringEvent->getFutureEvents() as $event) {
                    $event->copyFrom($recurringEvent);
                    $this->em->persist($event);
                }
            }
            $this->em->flush();

            $this->addFlash('success', sprintf('Updated event "%s"', $recurringEvent));
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
    public function delete(Request $request, RecurringEvent $recurringEvent): Response
    {
        $form = $this->createForm(ConfirmationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->deleteFutureEvents($recurringEvent);
            $this->em->remove($recurringEvent);
            $this->em->flush();

            $this->addFlash('success', sprintf('Deleted event "%s"', $recurringEvent));
            return $this->redirectToRoute('event_recurring_event_index');
        }

        return $this->render('general/confirmation.form.html.twig', [
            'message' => sprintf('Are you sure you want to delete event "%s"', $recurringEvent),
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete_events', name: '_delete_events')]
    public function deleteEvents(RecurringEvent $recurringEvent): Response
    {
        $count = $this->deleteFutureEvents($recurringEvent);
        $this->em->flush();
        $this->addFlash('success', sprintf('Deleted %d events', $count));

        return $this->redirectToRoute('event_recurring_event_index');
    }

    #[Route('/{id}/create_events', name: '_create_events')]
    public function createEvents(RecurringEvent $recurringEvent): Response
    {
        $count = $this->createNewEvents($recurringEvent);
        $this->em->flush();
        $this->addFlash('success', sprintf('Created %d events', $count));

        return $this->redirectToRoute('event_recurring_event_index');
    }

    private function deleteFutureEvents(RecurringEvent $recurringEvent): int
    {
        $deletedEventCount = 0;
        foreach ($recurringEvent->getFutureEvents() as $event) {
            $this->em->remove($event);
            $recurringEvent->removeEvent($event);
            $deletedEventCount++;
        }
        return $deletedEventCount;
    }

    private function createNewEvents(RecurringEvent $recurringEvent): int
    {
        $newEventCount = 0;
        $nextYear = new \DateTimeImmutable('+1 year');
        while ($event = $recurringEvent->createNextEvent() and $event->getStartDate() < $nextYear) {
            $this->em->persist($event);
            $newEventCount++;
        }
        // We need to remove the last event, because it is and should not persist
        $recurringEvent->removeEvent($event);
        return $newEventCount;
    }
}
