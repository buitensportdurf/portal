<?php

namespace App\Controller;

use App\Entity\ResetPasswordRequest;
use App\Entity\User;
use App\Exception\InvalidResetPasswordTokenException;
use App\Exception\TooManyPasswordRequestsException;
use App\Form\ChangePasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Repository\ResetPasswordRequestRepository;
use App\Repository\UserRepository;
use App\Service\EmailFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reset-password')]
class ResetPasswordController extends AbstractController
{
    public function __construct(
        private readonly ResetPasswordRequestRepository $repository,
        private readonly UserRepository                 $userRepository
    ) {}

    #[Route('', name: 'forgot_password_request')]
    public function request(
        Request                $request,
        MailerInterface        $mailer,
        EntityManagerInterface $em,
    ): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->userRepository->findOneBy(['email' => $form->get('email')->getData()]);

            $this->addFlash('success', 'An email has been sent with a reset password link.');
            // Do not reveal whether a user account was found or not.
            if (!$user) {
                return $this->redirectToRoute('login');
            }

            try {
                $resetToken = $this->generateResetToken($user);
                $em->persist($resetToken);
                $em->flush();
            } catch (TooManyPasswordRequestsException $e) {
                // If you want to tell the user why a reset email was not sent, uncomment
                // the lines below and change the redirect to 'forgot_password_request'.
                // Caution: This may reveal if a user is registered or not.
                //
                // $this->addFlash('reset_password_error', sprintf(
                //     '%s - %s',
                //     $translator->trans(ResetPasswordExceptionInterface::MESSAGE_PROBLEM_HANDLE, [], 'ResetPasswordBundle'),
                //     $translator->trans($e->getReason(), [], 'ResetPasswordBundle')
                // ));

                return $this->redirectToRoute('login');
            }

            $mailer->send(EmailFactory::resetPassword($user, $resetToken->getToken()));

            return $this->redirectToRoute('login');
        }

        return $this->render('reset_password/request.html.twig', [
            'requestForm' => $form->createView(),
        ]);
    }

    #[Route('/reset/{token}', name: 'reset_password')]
    public function reset(
        Request                     $request,
        UserPasswordHasherInterface $passwordHasher,
        string                      $token = null
    ): Response
    {
        if (null === $token) {
            throw $this->createNotFoundException('No reset password token found in the URL or in the session.');
        }

        try {
            $user = $this->validateTokenAndFetchUser($token);
        } catch (InvalidResetPasswordTokenException $e) {
            $this->addFlash('error', sprintf(
                'There was a problem validating your password reset request - %s',
                $e->getReason()
            ));

            return $this->redirectToRoute('forgot_password_request');
        }

        // The token is valid; allow the user to change their password.
        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // A password reset token should be used only once, remove it.
            $this->repository->findResetPasswordRequest($token);

            // Encode(hash) the plain password, and set it.
            $encodedPassword = $passwordHasher->hashPassword(
                $user,
                $form->get('plainPassword')->getData()
            );

            $user->setPassword($encodedPassword);
            $this->userRepository->add($user);

            return $this->redirectToRoute('login');
        }

        return $this->render('reset_password/reset.html.twig', [
            'resetForm' => $form->createView(),
        ]);
    }

    private function processSendingPasswordResetEmail(
        string                 $emailFormData,
        MailerInterface        $mailer,
        EntityManagerInterface $em,
    ): RedirectResponse {}

    private function validateTokenAndFetchUser(string $token): User
    {
//        $this->resetPasswordCleaner->handleGarbageCollection();

        if (40 !== \strlen($token)) {
            throw new InvalidResetPasswordTokenException();
        }

        $resetRequest = $this->repository->findResetPasswordRequest($token);

        if (null === $resetRequest) {
            throw new InvalidResetPasswordTokenException();
        }

        if ($resetRequest->isExpired()) {
            throw new InvalidResetPasswordTokenException();
        }

        $user = $resetRequest->getUser();

        if (false === hash_equals($resetRequest->getToken(), $token)) {
            throw new InvalidResetPasswordTokenException();
        }

        return $user;
    }

    private function generateResetToken(User $user): ResetPasswordRequest
    {
//        $this->resetPasswordCleaner->handleGarbageCollection();

        if ($availableAt = $this->hasUserHitThrottling($user)) {
            throw new TooManyPasswordRequestsException($availableAt);
        }

        $resetRequestLifetime = 2592000;
        $expiresAt = new \DateTime(sprintf('+%d seconds', $resetRequestLifetime));
        $token = bin2hex(random_bytes(20));

        return (new ResetPasswordRequest())
            ->setUser($user)
            ->setExpiresAt($expiresAt)
            ->setToken($token)
            ->setRequestedAt(new \DateTime())
        ;
    }

    private function hasUserHitThrottling(User $user): ?\DateTimeInterface
    {
        $requestThrottleTime = 3600; // 1 hour
        $lastRequestDate = $this->repository->getMostRecentNonExpiredRequestDate($user);

        if (null === $lastRequestDate) {
            return null;
        }

        $availableAt = (clone $lastRequestDate)->add(new \DateInterval("PT{$requestThrottleTime}S"));

        if ($availableAt > new \DateTime('now')) {
            return $availableAt;
        }

        return null;
    }
}
