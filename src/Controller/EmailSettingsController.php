<?php

namespace App\Controller;

use App\Form\User\EmailNotificationsType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

/**
 * Email settings opened from the signed link in event emails, without logging in.
 */
class EmailSettingsController extends AbstractController
{
    #[Route('/email-settings', name: 'email_settings')]
    public function edit(
        Request                $request,
        UriSigner              $uriSigner,
        UserRepository         $repository,
        EntityManagerInterface $em,
    ): Response
    {
        $id = $request->query->getString('user');
        $user = $uriSigner->checkRequest($request) && Ulid::isValid($id) ? $repository->find(Ulid::fromString($id)) : null;
        if ($user === null) {
            throw $this->createNotFoundException('This link is invalid.');
        }

        $form = $this->createFormBuilder($user)
            ->add('emailNotifications', EmailNotificationsType::class)
            ->add('save', SubmitType::class)
            ->getForm()
        ;
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Your email settings have been saved.');

            // The signed URL, query string included, so the page keeps working
            return $this->redirect($request->getUri(), Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/email_settings.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }
}
