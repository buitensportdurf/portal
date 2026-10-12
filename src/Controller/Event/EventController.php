<?php

namespace App\Controller\Event;

use App\Entity\Event\Event;
use App\Entity\User;
use App\Form\Event\EventType;
use App\Recurrence\Plan\ChangeType;
use App\Recurrence\Plan\OccurrenceChange;
use App\Recurrence\SeriesField;
use App\Recurrence\SeriesSynchronizer;
use App\Recurrence\SubscriberNotifier;
use App\Repository\Event\EventRepository;
use App\Repository\Event\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/event/event', name: 'event_event')]
class EventController extends AbstractController
{
    #[Route('/index', name: '_index')]
    public function index(
        Request         $request,
        EventRepository $eventRepository,
        TagRepository   $tagRepository
    ): Response
    {
        $tagName = $request->query->get('tag');
        $tag = $tagRepository->findOneBy(['name' => $tagName]);

        $includeUnpublished = $this->isGranted('ROLE_EVENT_EDIT');

        return $this->render('event/event/index.html.twig', [
            'events' => $eventRepository->findByTag($tag, $tagRepository->findIsDefaultHide(), $includeUnpublished),
            'tags' => $tagRepository->findAll(),
            'tag' => $tag,
        ]);
    }

    #[Route('/past', name: '_past')]
    public function past(Request $request, EventRepository $eventRepository): Response
    {
        $years = $eventRepository->findPastYears();
        $year = $request->query->getInt('year') ?: ($years[0] ?? null);

        return $this->render('event/event/past.html.twig', [
            'events' => $eventRepository->findPast($year, $this->isGranted('ROLE_EVENT_EDIT')),
            'years' => $years,
            'year' => $year,
        ]);
    }

    #[Route('/new', name: '_new')]
    #[IsGranted('ROLE_EVENT_EDIT')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $event = new Event();
        $event->published = false;
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

    #[Route('/{id}/show', name: '_show')]
    public function show(Event $event): Response
    {
        if (!$event->published && !$this->isGranted('ROLE_EVENT_EDIT')) {
            throw $this->createNotFoundException();
        }

        return $this->render('event/event/show.html.twig', [
            'event' => $event,
        ]);
    }

    #[Route('/{id}/edit', name: '_edit')]
    #[IsGranted('ROLE_EVENT_EDIT')]
    public function edit(Request $request, Event $event, EntityManagerInterface $entityManager): Response
    {
        $before = self::snapshot($event);
        $form = $this->createForm(EventType::class, $event);
        $form->add('Save', SubmitType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Whatever is changed here no longer follows the series
            if ($event->recurringEvent !== null) {
                $event->markOverridden(...array_filter(
                    SeriesField::cases(),
                    fn(SeriesField $field) => $field->read($event) !== $before[$field->value],
                ));
            }
            $entityManager->flush();

            return $this->redirectToRoute('event_event_show', ['id' => $event->id], Response::HTTP_SEE_OTHER);
        }

        return $this->render('event/event/edit.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/publish', name: '_publish')]
    #[IsGranted('publish', subject: 'event')]
    public function publish(Event $event, EntityManagerInterface $entityManager): Response
    {
        $event->published = true;
        if ($event->recurringEvent !== null) {
            $event->markOverridden(SeriesField::Published);
        }
        $entityManager->flush();

        $this->addFlash('success', sprintf('Event "%s" has been published.', $event));
        return $this->redirectToRoute('event_event_show', ['id' => $event->id]);
    }

    #[Route('/{id}/unpublish', name: '_unpublish')]
    #[IsGranted('unpublish', subject: 'event')]
    public function unpublish(Event $event, EntityManagerInterface $entityManager): Response
    {
        $event->published = false;
        if ($event->recurringEvent !== null) {
            $event->markOverridden(SeriesField::Published);
        }
        $entityManager->flush();

        $this->addFlash('success', sprintf('Event "%s" has been moved to draft.', $event));
        return $this->redirectToRoute('event_event_show', ['id' => $event->id]);
    }

    #[Route('/{id}/delete', name: '_delete')]
    #[IsGranted('ROLE_EVENT_EDIT')]
    public function delete(Event $event, EventRepository $repository): Response
    {
        $repository->remove($event);

        $this->addFlash('success', sprintf('Deleted event "%s"', $event));
        return $this->redirectToRoute('event_event_index');
    }

    /**
     * Cancels the event: it stays visible, subscribing is closed and subscribers are emailed.
     */
    #[Route('/{id}/cancel', name: '_cancel', methods: ['POST'])]
    #[IsGranted('ROLE_EVENT_EDIT')]
    public function cancel(
        Request                $request,
        Event                  $event,
        EntityManagerInterface $entityManager,
        SubscriberNotifier     $notifier,
    ): Response
    {
        $this->denyUnlessCsrf($request, 'event-cancel-' . $event->id);
        if (!$event->isCancelled()) {
            $change = new OccurrenceChange(ChangeType::Cancel, $event, $event->occurrenceStart ?? $event->startDate);
            SeriesSynchronizer::cancel($event);
            $entityManager->flush();
            if ($change->notifiesSubscribers()) {
                $notifier->notify($change);
            }
        }

        $this->addFlash('success', sprintf('Event "%s" has been cancelled.', $event));
        return $this->redirectToRoute('event_event_show', ['id' => $event->id], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/restore', name: '_restore', methods: ['POST'])]
    #[IsGranted('ROLE_EVENT_EDIT')]
    public function restore(Request $request, Event $event, EntityManagerInterface $entityManager): Response
    {
        $this->denyUnlessCsrf($request, 'event-restore-' . $event->id);
        $event->cancelledAt = null;
        $entityManager->flush();

        $this->addFlash('success', sprintf('Event "%s" is no longer cancelled.', $event));
        return $this->redirectToRoute('event_event_show', ['id' => $event->id], Response::HTTP_SEE_OTHER);
    }

    /**
     * Drops the event's customisations, so it follows its series again.
     */
    #[Route('/{id}/reset', name: '_reset', methods: ['POST'])]
    #[IsGranted('ROLE_EVENT_RECURRING')]
    public function reset(Request $request, Event $event, EntityManagerInterface $entityManager): Response
    {
        $this->denyUnlessCsrf($request, 'event-reset-' . $event->id);
        $series = $event->recurringEvent;
        if ($series === null || $event->occurrenceStart === null) {
            throw $this->createNotFoundException('This event is not an occurrence of a series');
        }
        foreach ($event->getOverriddenFields() as $field) {
            $field->apply($series, $event->occurrenceStart, $event);
        }
        $event->overriddenFields = [];
        $entityManager->flush();

        $this->addFlash('success', sprintf('Event "%s" follows its series again.', $event));
        return $this->redirectToRoute('event_event_show', ['id' => $event->id], Response::HTTP_SEE_OTHER);
    }

    /**
     * Takes the event out of its series, as a standalone event. The series skips its date from now on.
     */
    #[Route('/{id}/detach', name: '_detach', methods: ['POST'])]
    #[IsGranted('ROLE_EVENT_RECURRING')]
    public function detach(Request $request, Event $event, EntityManagerInterface $entityManager): Response
    {
        $this->denyUnlessCsrf($request, 'event-detach-' . $event->id);
        if ($series = $event->recurringEvent) {
            SeriesSynchronizer::release($event);
            $series->removeEvent($event);
            $event->overriddenFields = [];
            $entityManager->flush();
            $this->addFlash('success', sprintf('Event "%s" is now separate from "%s".', $event, $series));
        }

        return $this->redirectToRoute('event_event_show', ['id' => $event->id], Response::HTTP_SEE_OTHER);
    }

    private function denyUnlessCsrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }
    }

    /**
     * @return array<string, mixed> series field values keyed by field
     */
    private static function snapshot(Event $event): array
    {
        $values = [];
        foreach (SeriesField::cases() as $field) {
            $values[$field->value] = $field->read($event);
        }

        return $values;
    }
}
