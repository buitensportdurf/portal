<?php

namespace App\Controller;

use App\Entity\Event\Event;
use App\Entity\User;
use App\Form\User\GuestForm;
use App\Form\User\RegistrationFormType;
use App\Repository\UserRepository;
use App\Service\CommissionMailer;
use App\Service\EmailFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            $this->addFlash('error', 'You are already logged in, redirected you to the homepage');
            return $this->redirectToRoute('home');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('user/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/register', name: 'register')]
    public function register(
        Request                     $request,
        UserPasswordHasherInterface $userPasswordHasher,
        UserRepository              $repository,
        MailerInterface             $mailer,
        CommissionMailer            $commissionMailer,
    ): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $repoUser = $repository->findByEmail($user->email);
            if ($repoUser) {
                if ($repoUser->guest) {
                    $this->addFlash('success', 'Found an existing guest account based on email, upgrading to normal user');
                    $repoUser->username = $user->username;
                    $repoUser->name = $user->name;
                    $repoUser->guest = false;
                    $user = $repoUser;
                } else {
                    $this->addFlash('error', 'Email already used by a registered user');
                    return $this->redirectToRoute('register');
                }
            }

            $user->password = $userPasswordHasher->hashPassword(
                $user,
                $form->get('plainPassword')->getData()
            );
            $user->enabled = false;

            $repository->add($user);
            $mailer->send(EmailFactory::signupEmail($user));
            $commissionMailer->send(EmailFactory::newUserNotification($user));

            $this->addFlash('success', sprintf('Successfully registered user %s', $user->username));

            return $this->redirectToRoute('register');
        }

        return $this->render('user/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/createEventGuest/{id}', name: 'create_event_guest')]
    public function createEventGuest(
        Request         $request,
        Event           $event,
        UserRepository  $repository,
        MailerInterface  $mailer,
        CommissionMailer $commissionMailer,
    ): Response
    {
        if ($this->getUser()) {
            $this->addFlash('error', 'You are already logged in, redirected you to the homepage');
            return $this->redirectToRoute('home');
        }

        $user = new User();
        $user->username = sprintf('guest_%s', uniqid());
        $user->password = uniqid();
        $user->guest = true;

        $form = $this
            ->createForm(GuestForm::class, $user)
            ->add('Save', SubmitType::class)
        ;

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $repoUser = $repository->findByEmail($user->email);
            if ($repoUser) {
                if ($repoUser->guest) {
                    $user = $repoUser;
                } else {
                    $this->addFlash('error', 'Email already used by a registered user');
                    return $this->redirectToRoute('create_event_guest', [
                        'id' => $event->id,
                    ]);
                }
            } else {
                $repository->add($user);
            }

            $mailer->send(EmailFactory::eventGuestSignupEmail($user, $event));
            $commissionMailer->send(EmailFactory::newUserNotification($user));

            $this->addFlash('success', sprintf('Successfully registered guest user %s', $user->name));

            return $this->redirectToRoute('event_event_show', [
                'id' => $event->id,
            ]);
        }

        return $this->render('user/guest.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/loginEventGuest/{user}/{event}', name: 'login_event_guest')]
    public function loginEventGuest(
        User     $user,
        Event    $event,
        Security $security,
    ): Response
    {
        if (!$user->guest) {
            $this->addFlash('error', 'Logging in like this only works for a guest account');
            return $this->redirectToRoute('home');
        }
        $security->login($user, 'form_login');

        return $this->redirectToRoute('event_subscription_subscribe', [
            'id' => $event->id,
        ]);
    }
}
