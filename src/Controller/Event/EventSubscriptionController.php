<?php

namespace App\Controller\Event;

use App\Entity\Event\Event;
use App\Entity\Event\EventSubscription;
use App\Repository\Event\EventSubscriptionRepository;
use App\Security\Voter\EventSubscriptionVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/event/subscription', name: 'event_subscription')]
class EventSubscriptionController extends AbstractController
{
    public function __construct(
        private readonly EventSubscriptionRepository $repository,
    )
    {
    }

    #[Route('/subscribe/{id}', name: '_subscribe')]
    public function subscribe(Request $request, Event $event): Response
    {
        $this->denyAccessUnlessGranted(EventSubscriptionVoter::SUBSCRIBE, $event);

        $subscription = new EventSubscription();
        $subscription->setCreatedDateNowNoSeconds()
            ->setEvent($event)
            ->setAmount(1)
            ->setCreatedUser($this->getUser());

        $form = $this->createFormBuilder($subscription)
            ->add('amount');

        if ($this->isGranted('ROLE_EVENT_ADMIN')) {
            $form->add('createdUser');
        }
        $form->add('note')
            ->add('Subscribe', SubmitType::class);
        $form = $form->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($event->isSubscribed($subscription->getCreatedUser())) {
                $this->addFlash('error', sprintf('User %s is already subscribed to %s', $subscription->getCreatedUser(), $event));
                return $this->redirectToRoute('event_event_show', ['id' => $event->getId()]);
            }

            $this->repository->save($subscription);
            $this->addFlash('success', sprintf('You have subscribed to %s', $event));
            return $this->redirectToRoute('event_event_show', ['id' => $event->getId()]);
        }

        return $this->render('event/subscription/subscribe.html.twig', [
            'form' => $form->createView(),
            'event' => $event,
        ]);
    }

    #[Route('/unsubscribe/{id}', name: '_unsubscribe')]
    public function unsubscribe(EventSubscription $subscription): Response
    {
        $this->denyAccessUnlessGranted(EventSubscriptionVoter::UNSUBSCRIBE, $subscription->getEvent());

        $event = $subscription->getEvent();
        $this->repository->delete($subscription);
        $this->addFlash('success', sprintf('You have unsubscribed from %s', $event));
        return $this->redirectToRoute('event_event_show', ['id' => $event->getId()]);
    }
}