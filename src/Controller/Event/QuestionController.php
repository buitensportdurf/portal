<?php

namespace App\Controller\Event;

use App\Entity\Event\Event;
use App\Entity\Event\Question;
use App\Form\Event\QuestionType;
use App\Repository\Event\QuestionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/event/question', name: 'event_question')]
class QuestionController extends AbstractController
{
    public function __construct(
        private readonly QuestionRepository $questionRepository,
    ) {}

    #[Route('/create/{id}', name: '_create')]
    public function create(Event $event, Request $request): Response
    {
        $question = new Question();
        $question->event = $event;

        $form = $this->createForm(QuestionType::class, $question);
        $form->add('save', SubmitType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->questionRepository->save($question);

            $this->addFlash('success', sprintf('Added question "%s" to event "%s"', $question->question, $event));
            return $this->redirectToRoute('event_event_show', ['id' => $event->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('event/question/create.html.twig', [
            'event' => $event,
            'question' => $question,
            'form' => $form,
        ]);
    }

    #[Route('/edit/{id}', name: '_edit')]
    public function edit(Question $question, Request $request): Response
    {
        $form = $this->createForm(QuestionType::class, $question);
        $form->add('save', SubmitType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->questionRepository->save($question);

            $this->addFlash('success', sprintf('Saved question "%s" to event "%s"', $question->question, $question->event));
            return $this->redirectToRoute('event_event_show', ['id' => $question->event->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('event/question/edit.html.twig', [
            'event' => $question->event,
            'question' => $question,
            'form' => $form,
        ]);
    }

    #[Route('/delete/{id}', name: '_delete')]
    public function delete(Question $question): Response
    {
        $event = $question->event;
        $questionText = $question->question;
        $this->questionRepository->remove($question);

        $this->addFlash('success', sprintf('Deleted question "%s" from event "%s"', $questionText, $question->event));
        return $this->redirectToRoute('event_event_show', ['id' => $event->getId()], Response::HTTP_SEE_OTHER);
    }
}