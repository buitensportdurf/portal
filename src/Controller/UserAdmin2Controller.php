<?php

namespace App\Controller;

use App\Entity\Event\EventSubscription;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EmailFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Cache\CacheInterface;

use function Symfony\Component\Translation\t;

#[Route('/admin2/user', name: 'admin2_user')]
#[IsGranted('ROLE_ADMIN_USER')]
class UserAdmin2Controller extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly CacheInterface $cache,
        private readonly EmailFactory   $emailFactory,
    ) {}

    #[Route('/index', name: '_index')]
    public function index(): Response
    {
        return $this->render('admin/user/index.html.twig', [
            'users' => $this->userRepository->findDisabled(),
        ]);
    }

    #[Route('/{id}/enable', name: '_enable', methods: ['POST'])]
    #[IsCsrfTokenValid('user-enable')]
    public function enable(User $user, MailerInterface $mailer): Response
    {
        $user->enabled = true;
        $this->userRepository->add($user);
        $this->cache->delete('pending_activation_count');
        $mailer->send($this->emailFactory->userEnabled($user));
        $this->addFlash('success', t('{name} is now enabled', ['name' => $user->name]));

        return $this->redirectToRoute('admin2_user_index');
    }

    /**
     * Turns down a pending registration. A former guest keeps their subscriptions as a guest
     * again; anyone else is deleted.
     */
    #[Route('/{id}/reject', name: '_reject', methods: ['POST'])]
    #[IsCsrfTokenValid('user-reject')]
    public function reject(User $user, EntityManagerInterface $em): Response
    {
        if ($user->enabled) {
            $this->addFlash('error', t('{name} is already active', ['name' => $user->name]));
            return $this->redirectToRoute('admin2_user_index');
        }

        if ($em->getRepository(EventSubscription::class)->count(['createdUser' => $user]) > 0) {
            $user->guest = true;
            $user->enabled = true;
            $user->password = '!guest';
            $this->addFlash('success', t('Rejected {name}; they keep their event subscriptions as a guest', ['name' => $user->name]));
        } else {
            $em->remove($user);
            $this->addFlash('success', t('Rejected and removed the registration of {name}', ['name' => $user->name]));
        }
        $em->flush();
        $this->cache->delete('pending_activation_count');

        return $this->redirectToRoute('admin2_user_index');
    }

    #[Route('/{id}/switch', name: '_switch')]
    public function switch(User $user): Response
    {
        $this->addFlash('success', t('Now impersonating {name}', ['name' => $user->name]));
        return $this->redirectToRoute('home', ['_switch_user' => $user->username]);
    }
}
