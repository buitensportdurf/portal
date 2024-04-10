<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ConfirmationType;
use App\Repository\UserRepository;
use App\Service\EmailFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/user', name: 'admin_user')]
class UserAdminController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}

    #[Route('/index', name: '_index')]
    public function index(): Response
    {
        return $this->render('admin/user/index.html.twig', [
            'users' => $this->userRepository->findDisabled(),
        ]);
    }

    #[Route('/{id}/enable', name: '_enable')]
    public function enable(User $user, Request $request, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ConfirmationType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user->setEnabled(true);
            $this->userRepository->add($user);
            $mailer->send(EmailFactory::userEnabled($user));
            $this->addFlash('success', sprintf('%s is now enabled', $user->getName()));

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('general/confirmation.form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Enable ' . $user->getName(),
            'message' => sprintf('Do you really want to enable "%s" with email "%s"?', $user->getName(), $user->getEmail()),
        ]);
    }
}