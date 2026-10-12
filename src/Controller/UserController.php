<?php

namespace App\Controller;

use App\Entity\User;
use App\EventListener\LocaleListener;
use App\Form\User\ProfileType;
use App\Repository\UserRepository;
use App\Service\EmailFactory;
use App\Service\LastRelease;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function Symfony\Component\Translation\t;

#[Route('/user', name: 'user')]
#[IsGranted('ROLE_USER')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository         $repository,
        private readonly MailerInterface        $mailer,
        private readonly UriSigner              $uriSigner,
        private readonly EmailFactory           $emailFactory,
    ) {}

    #[Route('/profile', name: '_profile')]
    public function profile(
        Request                     $request,
        UserPasswordHasherInterface $userPasswordHasher,
        LastRelease                 $lastRelease,
    ): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user->guest) {
            throw $this->createAccessDeniedException('You cannot access this page with a guest account');
        }

        $user->locale ??= $request->getLocale();
        $form = $this->createForm(ProfileType::class, $user, ['current_email' => $user->email]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newEmail = trim((string) $form->get('newEmail')->getData());
            $newPassword = $form->get('plainPassword')->getData();
            $changesEmail = strcasecmp($newEmail, (string) $user->email) !== 0;

            // Someone at an unlocked computer must not be able to lock the owner out
            if (($changesEmail || $newPassword)
                && !$userPasswordHasher->isPasswordValid($user, (string) $form->get('currentPassword')->getData())) {
                $form->get('currentPassword')->addError(new FormError('Enter your current password to change your email or password.'));
            } elseif ($changesEmail && $this->isTaken($newEmail, $user)) {
                $form->get('newEmail')->addError(new FormError('This email address is already in use.'));
            } else {
                if ($newPassword) {
                    $user->password = $userPasswordHasher->hashPassword($user, $newPassword);
                    $this->mailer->send($this->emailFactory->passwordChanged($user));
                    $this->addFlash('success', t('Your password has been changed.'));
                }
                $this->em->flush();
                $request->getSession()->set(LocaleListener::SESSION_KEY, $user->locale);

                if ($changesEmail) {
                    $this->mailer->send($this->emailFactory->confirmEmailChange($user, $newEmail, $this->confirmUrl($user, $newEmail)));
                    $this->addFlash('success', t('We sent a confirmation link to {email}. Your email changes once you click it.', ['email' => $newEmail]));
                } else {
                    $this->addFlash('success', t('Profile updated.'));
                }

                return $this->redirectToRoute('user_profile', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('user/profile.html.twig', [
            'form' => $form->createView(),
            'release' => $lastRelease,
        ]);
    }

    /**
     * Confirms a new email address from the signed link sent to it. The link is tied to the account
     * and the address, expires after a day and only works for the logged-in owner.
     */
    #[Route('/email/confirm', name: '_email_confirm')]
    public function confirmEmail(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $newEmail = $request->query->getString('email');

        if (!$this->uriSigner->checkRequest($request) || $request->query->getString('user') !== (string) $user->id) {
            $this->addFlash('error', t('This confirmation link is invalid or has expired. Change your email again to get a new one.'));
            return $this->redirectToRoute('user_profile');
        }
        if ($this->isTaken($newEmail, $user)) {
            $this->addFlash('error', t('This email address is already in use by another account.'));
            return $this->redirectToRoute('user_profile');
        }

        $oldEmail = $user->email;
        $user->email = $newEmail;
        $this->em->flush();
        if ($oldEmail && strcasecmp($oldEmail, $newEmail) !== 0) {
            $this->mailer->send($this->emailFactory->emailChanged($user, $oldEmail));
        }

        $this->addFlash('success', t('Your email address is now {email}.', ['email' => $newEmail]));
        return $this->redirectToRoute('user_profile');
    }

    private function confirmUrl(User $user, string $newEmail): string
    {
        return $this->uriSigner->sign(
            $this->generateUrl('user_email_confirm', ['user' => (string) $user->id, 'email' => $newEmail], UrlGeneratorInterface::ABSOLUTE_URL),
            new \DateInterval('P1D'),
        );
    }

    /**
     * An address is taken when another account uses it as email, or as username (old usernames can
     * contain an "@" and win at login).
     */
    private function isTaken(string $email, User $user): bool
    {
        foreach ([$this->repository->findByEmail($email), $this->repository->findOneBy(['username' => $email])] as $other) {
            if ($other !== null && $other !== $user) {
                return true;
            }
        }

        return false;
    }
}
