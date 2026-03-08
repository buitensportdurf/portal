<?php

namespace App\Controller\Event;

use App\Entity\Event\Event;
use App\Entity\Event\EventSubscription;
use App\Entity\Event\QuestionAnswer;
use App\Entity\User;
use App\Form\Event\EventSubscriptionType;
use App\Form\Event\QuestionAnswerType;
use App\Repository\Event\EventSubscriptionRepository;
use App\Repository\Event\QuestionAnswerRepository;
use App\Security\Voter\EventSubscriptionVoter;
use App\Security\Voter\EventVoter;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/event/subscription', name: 'event_subscription')]
class EventSubscriptionController extends AbstractController
{
    public function __construct(
        private readonly EventSubscriptionRepository $repository,
    ) {}

    #[Route('/subscribe/{id}', name: '_subscribe')]
    public function subscribe(
        Request                  $request,
        Event                    $event,
        QuestionAnswerRepository $answerRepository,
    ): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Redirect if already subscribed
        if ($user && $event->isSubscribed($user) && !$this->isGranted('ROLE_EVENT_ADMIN')) {
            return $this->redirectToRoute('event_subscription_edit', [
                'id' => $event->getSubscription($user)->id,
            ]);
        }

        // Check permissions
        if (!$this->isGranted(EventVoter::SUBSCRIBE, $event)) {
            if ($user) {
                $this->addFlash('error', 'You cannot subscribe to this event');
                return $this->redirectToRoute('event_event_show', [
                    'id' => $event->id,
                ]);
            } else {
                return $this->redirectToRoute('event_subscription_nologin', [
                    'id' => $event->id,
                ]);
            }
        }

        $subscription = new EventSubscription();
        $subscription->setCreatedDateNowNoSeconds();
        $subscription->event = $event;
        $subscription->amount = 1;
        $subscription->createdUser = $user;

        foreach ($event->questions as $question) {
            $answer = new QuestionAnswer();
            $answer->question = $question;
            $answer->subscription = $subscription;
        }

        $form = $this->createForm(EventSubscriptionType::class, $subscription);

        if ($this->isGranted('ROLE_EVENT_ADMIN')) {
            $form->add('createdUser', options: [
                'query_builder' => fn($er) => $er->createQueryBuilder('u')->orderBy('u.name', 'ASC'),
                'label' => 'To subscribe user',
            ]);
        }
        $form->add('subscribe', SubmitType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->isGranted('ROLE_EVENT_ADMIN') && $user !== $subscription->createdUser) {
                $this->addFlash('error', 'You can only subscribe to your own events');
                return $this->redirectToRoute('event_event_show', ['id' => $event->id]);
            }

            $this->repository->save($subscription);
            $this->addFlash('success', sprintf('You have subscribed to %s', $event));
            return $this->redirectToRoute('event_event_show', ['id' => $event->id]);
        }

        return $this->render('event/subscription/subscribe.html.twig', [
            'form' => $form->createView(),
            'event' => $event,
        ]);
    }

    #[Route('/unsubscribe/{id}', name: '_unsubscribe')]
    #[IsGranted('ROLE_USER')]
    public function unsubscribe(EventSubscription $subscription): Response
    {
        $this->denyAccessUnlessGranted(EventVoter::UNSUBSCRIBE, $subscription->event);
        $event = $subscription->event;
        $this->repository->delete($subscription);
        $this->addFlash('success', sprintf('You have unsubscribed from %s', $event));
        return $this->redirectToRoute('event_event_show', ['id' => $event->id]);
    }

    #[Route('/{id}/edit', name: '_edit')]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(EventSubscriptionVoter::EDIT, subject: 'subscription')]
    public function edit(EventSubscription $subscription, Request $request): Response
    {
        // Add missing question answers
        foreach ($subscription->event->questions as $question) {
            $found = false;
            foreach ($subscription->questionAnswers as $answer) {
                if ($answer->question === $question) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $answer = new QuestionAnswer();
                $answer->question = $question;
                $answer->subscription = $subscription;
            }
        }

        $form = $this->createForm(EventSubscriptionType::class, $subscription)
            ->add('save', SubmitType::class)
        ;

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->repository->save($subscription);
            $this->addFlash('success', sprintf('Updated subscription to %s', $subscription->event));
            return $this->redirectToRoute('event_event_show', ['id' => $subscription->event->id]);
        }

        return $this->render('event/subscription/edit.html.twig', [
            'subscription' => $subscription,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/nologin/{id}', name: '_nologin')]
    public function notLoggedIn(Event $event): Response
    {
        return $this->render('event/subscription/not_logged_in.html.twig', [
            'event' => $event,
        ]);
    }
}
