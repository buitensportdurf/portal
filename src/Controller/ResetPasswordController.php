<?php

namespace App\Controller;

use App\Entity\ResetPasswordRequest;
use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Repository\ResetPasswordRequestRepository;
use App\Repository\UserRepository;
use App\Service\EmailFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

use function Symfony\Component\Translation\t;

#[Route('/reset-password')]
class ResetPasswordController extends AbstractController
{
    private const string LIFETIME = '+1 hour';
    private const string THROTTLE = '-15 minutes';

    public function __construct(
        private readonly ResetPasswordRequestRepository $repository,
        private readonly UserRepository                 $userRepository,
        private readonly EntityManagerInterface         $em,
        private readonly MailerInterface                $mailer,
        private readonly EmailFactory                   $emailFactory,
    ) {}

    /**
     * Always answers the same, so the form does not reveal which addresses have an account.
     */
    #[Route('', name: 'forgot_password_request')]
    public function request(
        Request                     $request,
        #[Target('password_reset')]
        RateLimiterFactoryInterface $limiter,
    ): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$limiter->create($request->getClientIp())->consume()->isAccepted()) {
                $this->addFlash('error', t('Too many requests from your network, please try again later.'));
                return $this->redirectToRoute('forgot_password_request');
            }

            $user = $this->userRepository->findByEmail((string) $form->get('email')->getData());
            if ($user !== null && !$user->guest && !$this->recentlyRequested($user)) {
                $token = bin2hex(random_bytes(20));
                $resetRequest = new ResetPasswordRequest();
                $resetRequest->user = $user;
                $resetRequest->token = ResetPasswordRequestRepository::hash($token);
                $resetRequest->requestedAt = new \DateTimeImmutable();
                $resetRequest->expiresAt = new \DateTimeImmutable(self::LIFETIME);
                $this->repository->removeExpiredResetPasswordRequests();
                $this->em->persist($resetRequest);
                $this->em->flush();

                $this->mailer->send($this->emailFactory->resetPassword($user, $token));
            }

            return $this->render('reset_password/check_email.html.twig', [
                'email' => $form->get('email')->getData(),
            ]);
        }

        return $this->render('reset_password/request.html.twig', [
            'requestForm' => $form->createView(),
        ]);
    }

    #[Route('/reset/{token}', name: 'reset_password')]
    public function reset(
        Request                     $request,
        UserPasswordHasherInterface $passwordHasher,
        string                      $token,
    ): Response
    {
        $resetRequest = $this->repository->findResetPasswordRequest($token);
        if ($resetRequest === null || $resetRequest->isExpired()) {
            $this->addFlash('error', t('This reset link is invalid or has expired. Request a new one below.'));

            return $this->redirectToRoute('forgot_password_request');
        }

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $resetRequest->user;
            $user->password = $passwordHasher->hashPassword($user, $form->get('plainPassword')->getData());
            // Links work once: drop every open request of this user
            $this->repository->removeResetPasswordRequest($resetRequest);
            $this->userRepository->add($user);
            $this->mailer->send($this->emailFactory->passwordChanged($user));

            $this->addFlash('success', t('Your password has been changed. You can now log in.'));

            return $this->redirectToRoute('login');
        }

        return $this->render('reset_password/reset.html.twig', [
            'resetForm' => $form->createView(),
        ]);
    }

    private function recentlyRequested(User $user): bool
    {
        $last = $this->repository->getMostRecentNonExpiredRequestDate($user);

        return $last !== null && $last > new \DateTimeImmutable(self::THROTTLE);
    }
}
