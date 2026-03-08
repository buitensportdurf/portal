<?php

namespace App\Controller\Event;

use App\Entity\Event\Event;
use App\Entity\User;
use App\Form\Event\EventType;
use App\Repository\Event\EventRepository;
use App\Repository\Event\TagRepository;
use DateInterval;
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
        $event->setDuration(new DateInterval('PT0S'));
        $event->setPublished(false);
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
        if (!$event->isPublished() && !$this->isGranted('ROLE_EVENT_EDIT')) {
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
        $form = $this->createForm(EventType::class, $event);
        $form->add('Save', SubmitType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('event_event_show', ['id' => $event->getId()], Response::HTTP_SEE_OTHER);
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
        $event->setPublished(true);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Event "%s" has been published.', $event));
        return $this->redirectToRoute('event_event_show', ['id' => $event->getId()]);
    }

    #[Route('/{id}/unpublish', name: '_unpublish')]
    #[IsGranted('unpublish', subject: 'event')]
    public function unpublish(Event $event, EntityManagerInterface $entityManager): Response
    {
        $event->setPublished(false);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Event "%s" has been moved to draft.', $event));
        return $this->redirectToRoute('event_event_show', ['id' => $event->getId()]);
    }

    #[Route('/{id}/delete', name: '_delete')]
    #[IsGranted('ROLE_EVENT_EDIT')]
    public function delete(Event $event, EventRepository $repository): Response
    {
        $repository->remove($event);

        $this->addFlash('success', sprintf('Deleted event "%s"', $event));
        return $this->redirectToRoute('event_event_index');
    }
}
