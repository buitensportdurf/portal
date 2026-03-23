<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EmailFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Cache\CacheInterface;

#[Route('/admin2/user', name: 'admin2_user')]
#[IsGranted('ROLE_ADMIN_USER')]
class UserAdmin2Controller extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly CacheInterface $cache,
    ) {}

    #[Route('/index', name: '_index')]
    public function index(): Response
    {
        return $this->render('admin/user/index.html.twig', [
            'users' => $this->userRepository->findDisabled(),
        ]);
    }

    #[Route('/{id}/enable', name: '_enable')]
    public function enable(User $user, MailerInterface $mailer): Response
    {
        $user->enabled = true;
        $this->userRepository->add($user);
        $this->cache->delete('pending_activation_count');
        $mailer->send(EmailFactory::userEnabled($user));
        $this->addFlash('success', sprintf('%s is now enabled', $user->name));

        return $this->redirectToRoute('admin2_user_index');
    }

    #[Route('/{id}/switch', name: '_switch')]
    public function switch(User $user): Response
    {
        $this->addFlash('success', sprintf('Now impersonating %s', $user->name));
        return $this->redirectToRoute('home', ['_switch_user' => $user->username]);
    }
}
