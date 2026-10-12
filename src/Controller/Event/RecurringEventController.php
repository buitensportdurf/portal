<?php

namespace App\Controller\Event;

use App\Entity\Event\RecurringEvent;
use App\Entity\Event\Tag;
use App\Form\Event\RecurringEventType;
use App\Recurrence\Plan\ChangeType;
use App\Recurrence\Plan\SeriesPlan;
use App\Recurrence\SeriesPlanner;
use App\Recurrence\SeriesSynchronizer;
use App\Repository\Event\RecurringEventRepository;
use App\Repository\Event\TagRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

#[Route('/event/recurring_event', name: 'event_recurring_event')]
#[IsGranted('ROLE_EVENT_RECURRING')]
class RecurringEventController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface   $em,
        private readonly RecurringEventRepository $repository,
        private readonly SeriesPlanner            $planner,
        private readonly SeriesSynchronizer       $synchronizer,
        private readonly TranslatorInterface      $translator,
    ) {}

    #[Route('/index', name: '_index')]
    public function index(): Response
    {
        return $this->render('event/recurring_event/index.html.twig', [
            'recurringEvents' => $this->repository->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: '_new')]
    public function new(Request $request, TagRepository $tagRepository): Response
    {
        $recurringEvent = new RecurringEvent();
        $recurringEvent->rrule = 'FREQ=WEEKLY';
        if ($recurringTag = $tagRepository->find(Tag::ID_RECURRING)) {
            $recurringEvent->addTag($recurringTag);
        }
        $form = $this->createForm(RecurringEventType::class, $recurringEvent);
        $form->add('save', SubmitType::class, ['label' => t('Create series')]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($recurringEvent);
            $plan = $this->planner->planExtension($recurringEvent);
            $this->synchronizer->apply($plan);

            $this->addFlash('success', t('Created "{name}" with {count, plural, one {# event} other {# events}}', ['name' => (string) $recurringEvent, 'count' => count($plan->occurrences)]));
            return $this->redirectToRoute('event_recurring_event_show', ['id' => $recurringEvent->id], Response::HTTP_SEE_OTHER);
        }

        return $this->render('event/recurring_event/new.html.twig', [
            'recurringEvent' => $recurringEvent,
            'form' => $form,
            'plan' => $form->isSubmitted() ? null : $this->planner->planExtension($recurringEvent),
        ]);
    }

    /**
     * Live preview while filling in the new-series form: the dates that would be created.
     */
    #[Route('/new/preview', name: '_new_preview', methods: ['POST'])]
    public function newPreview(Request $request): Response
    {
        $recurringEvent = new RecurringEvent();
        $form = $this->createForm(RecurringEventType::class, $recurringEvent);
        $form->handleRequest($request);

        return $this->renderPreview($form, fn() => $this->planner->planExtension($recurringEvent), true);
    }

    #[Route('/{id}/edit', name: '_edit')]
    public function edit(Request $request, RecurringEvent $recurringEvent): Response
    {
        $draft = clone $recurringEvent;
        $form = $this->createEditForm($recurringEvent, $draft, $request->query->get('from'));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plan = $this->planner->plan($recurringEvent, $draft, self::applyFrom($form));
            if ($plan->isEmpty()) {
                $this->addFlash('success', t('Nothing changed'));
                return $this->redirectToRoute('event_recurring_event_show', ['id' => $recurringEvent->id], Response::HTTP_SEE_OTHER);
            }
            $notify = (bool) $form->get('notifySubscribers')->getData();
            $owner = $this->synchronizer->apply($plan, $notify);

            $this->addFlash('success', $this->summary($plan, $notify));
            return $this->redirectToRoute('event_recurring_event_show', ['id' => $owner->id], Response::HTTP_SEE_OTHER);
        }

        return $this->render('event/recurring_event/edit.html.twig', [
            'recurringEvent' => $recurringEvent,
            'form' => $form,
            'plan' => $form->isSubmitted() ? null : $this->planner->plan($recurringEvent, $draft),
        ]);
    }

    /**
     * Live changelist while editing: what saving the form as it is now would do.
     */
    #[Route('/{id}/preview', name: '_preview', methods: ['POST'])]
    public function preview(Request $request, RecurringEvent $recurringEvent): Response
    {
        $draft = clone $recurringEvent;
        $form = $this->createEditForm($recurringEvent, $draft);
        $form->handleRequest($request);

        return $this->renderPreview($form, fn() => $this->planner->plan($recurringEvent, $draft, self::applyFrom($form)), false);
    }

    #[Route('/{id}/show', name: '_show')]
    public function show(RecurringEvent $recurringEvent): Response
    {
        return $this->render('event/recurring_event/show.html.twig', [
            'recurringEvent' => $recurringEvent,
            'extension' => $this->planner->planExtension($recurringEvent),
        ]);
    }

    /**
     * Tops the series up to the horizon now instead of waiting for the nightly run.
     */
    #[Route('/{id}/generate', name: '_generate', methods: ['POST'])]
    public function generate(Request $request, RecurringEvent $recurringEvent): Response
    {
        $this->denyUnlessCsrf($request, 'recurring-generate-' . $recurringEvent->id);
        $plan = $this->planner->planExtension($recurringEvent);
        $this->synchronizer->apply($plan);

        $this->addFlash('success', t('Created {count, plural, one {# event} other {# events}}', ['count' => count($plan->occurrences)]));
        return $this->redirectToRoute('event_recurring_event_show', ['id' => $recurringEvent->id], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/delete', name: '_delete', methods: ['GET', 'POST'])]
    public function delete(Request $request, RecurringEvent $recurringEvent): Response
    {
        $plan = $this->planner->planRemoval($recurringEvent);

        if ($request->isMethod('POST')) {
            $this->denyUnlessCsrf($request, 'recurring-delete-' . $recurringEvent->id);
            $notify = $request->request->has('notify');
            $this->synchronizer->apply($plan, $notify);

            $this->addFlash('success', $this->summary($plan, $notify, t('Deleted "{name}".', ['name' => (string) $recurringEvent])));
            return $this->redirectToRoute('event_recurring_event_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('event/recurring_event/delete.html.twig', [
            'recurringEvent' => $recurringEvent,
            'plan' => $plan,
        ]);
    }

    private function createEditForm(RecurringEvent $recurringEvent, RecurringEvent $draft, ?string $from = null): FormInterface
    {
        $choices = [];
        foreach ($recurringEvent->getFutureEvents() as $event) {
            if ($event->occurrenceStart !== null && !$event->isCancelled()) {
                $date = $event->occurrenceStart->format('Y-m-d');
                $choices[$date] = $date;
            }
        }
        // The first upcoming occurrence is the same as "all upcoming"
        array_shift($choices);

        $form = $this->createForm(RecurringEventType::class, $draft, [
            'apply_from_choices' => $choices,
            'apply_from' => in_array($from, $choices, true) ? $from : null,
            'notify_option' => true,
        ]);
        $form->add('save', SubmitType::class, ['label' => t('Save changes')]);

        return $form;
    }

    /**
     * @param \Closure(): SeriesPlan $plan
     */
    private function renderPreview(FormInterface $form, \Closure $plan, bool $isNew): Response
    {
        $errors = [];
        if (!$form->isSubmitted() || !$form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                $label = $error->getOrigin()?->getConfig()->getOption('label');
                if ($label instanceof TranslatableInterface) {
                    $label = $label->trans($this->translator);
                } elseif (is_string($label) && $label !== '') {
                    $label = $this->translator->trans($label);
                }
                $errors[] = ($label ? $label . ': ' : '') . $error->getMessage();
            }
        }

        // Nothing is flushed here: the form is bound to a detached draft (or an unpersisted series)
        return $this->render('event/recurring_event/_preview.html.twig', [
            'plan' => $errors === [] ? $plan() : null,
            'errors' => $errors,
            'isNew' => $isNew,
        ]);
    }

    private static function applyFrom(FormInterface $form): ?DateTimeImmutable
    {
        $from = $form->has('applyFrom') ? $form->get('applyFrom')->getData() : null;

        return $from ? new DateTimeImmutable($from) : null;
    }

    private function denyUnlessCsrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }
    }

    /**
     * One sentence per kind of change, e.g. "2 events created. 1 event cancelled. 3 subscribers notified."
     */
    private function summary(SeriesPlan $plan, bool $notified = true, ?TranslatableInterface $intro = null): string
    {
        $sentences = $intro ? [$intro] : [];
        $counts = [
            t('{count, plural, one {# event} other {# events}} created.', ['count' => $plan->count(ChangeType::Create)]),
            t('{count, plural, one {# event} other {# events}} moved.', ['count' => $plan->count(ChangeType::Move)]),
            t('{count, plural, one {# event} other {# events}} updated.', ['count' => $plan->count(ChangeType::Update)]),
            t('{count, plural, one {# event} other {# events}} cancelled.', ['count' => $plan->count(ChangeType::Cancel)]),
            t('{count, plural, one {# event} other {# events}} deleted.', ['count' => $plan->count(ChangeType::Delete)]),
        ];
        $counts = array_filter($counts, fn(TranslatableMessage $sentence) => $sentence->getParameters()['count'] > 0);
        if ($counts === []) {
            $sentences[] = t('Series updated, no events affected.');
        }
        array_push($sentences, ...$counts);
        if ($notified && $plan->notifiedSubscriberCount() > 0) {
            $sentences[] = t('{count, plural, one {# subscriber} other {# subscribers}} notified.', ['count' => $plan->notifiedSubscriberCount()]);
        }

        return implode(' ', array_map(fn(TranslatableInterface $sentence) => $sentence->trans($this->translator), $sentences));
    }
}
