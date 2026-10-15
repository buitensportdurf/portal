<?php

namespace App\Controller;

use App\Entity\Event\Event;
use App\Entity\Language;
use App\Entity\User;
use App\Form\User\GuestForm;
use App\Form\User\GuestUpgradeType;
use App\Form\User\RegistrationFormType;
use App\Repository\UserRepository;
use App\Security\LocalPath;
use App\Service\CommissionMailer;
use App\Service\EmailFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;
use Symfony\Contracts\Cache\CacheInterface;

use function Symfony\Component\Translation\t;

class SecurityController extends AbstractController
{
    public function __construct(
        private readonly UserRepository            $repository,
        private readonly MailerInterface           $mailer,
        private readonly CommissionMailer          $commissionMailer,
        private readonly LoginLinkHandlerInterface $loginLinkHandler,
        private readonly CacheInterface            $cache,
        private readonly EmailFactory              $emailFactory,
    ) {}

    #[Route('/login', name: 'login')]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            $this->addFlash('error', t('You are already logged in, redirected you to the homepage'));
            return $this->redirectToRoute('home');
        }

        return $this->render('user/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'target_path' => LocalPath::filter($request->query->get('_target_path')),
        ]);
    }

    /**
     * Handled by the login_link authenticator; this never runs.
     */
    #[Route('/login/link', name: 'login_link_check')]
    public function loginLinkCheck(): never
    {
        throw new \LogicException('The login_link authenticator handles this route.');
    }

    /**
     * Every outcome (new account, existing member, existing guest) looks the same to the visitor;
     * the email tells the owner of the address what happens next. That way the form does not
     * reveal which addresses have an account, and nobody can take over a guest account by
     * registering with its email.
     */
    #[Route('/register', name: 'register')]
    public function register(
        Request                     $request,
        UserPasswordHasherInterface $userPasswordHasher,
        #[Target('registration')]
        RateLimiterFactoryInterface $limiter,
    ): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$limiter->create($request->getClientIp())->consume()->isAccepted()) {
                $this->addFlash('error', t('Too many registrations from your network, please try again later.'));
                return $this->redirectToRoute('register');
            }
            // Bots fill in the hidden field; pretend it worked
            if ($form->get('website')->getData()) {
                return $this->checkEmail($request, (string) $user->email, 'register');
            }

            $existing = $this->repository->findByEmail($user->email);
            if ($existing?->guest) {
                $this->mailer->send($this->emailFactory->guestUpgrade($existing, $this->loginLink($existing, 'user_upgrade')));
            } elseif ($existing) {
                $this->mailer->send($this->emailFactory->accountExists($existing));
            } else {
                $user->password = $userPasswordHasher->hashPassword($user, $form->get('plainPassword')->getData());
                $user->enabled = false;
                $user->locale = Language::fromRequest($request);
                $this->repository->add($user);
                $this->cache->delete('pending_activation_count');
                $this->mailer->send($this->emailFactory->signupEmail($user));
                $this->commissionMailer->send($this->emailFactory->newUserNotification($user));
            }

            return $this->checkEmail($request, $user->email, 'register');
        }

        return $this->render('user/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/createEventGuest/{id}', name: 'create_event_guest')]
    public function createEventGuest(
        Request                     $request,
        Event                       $event,
        #[Target('guest_signup')]
        RateLimiterFactoryInterface $limiter,
    ): Response
    {
        if ($this->getUser()) {
            $this->addFlash('error', t('You are already logged in, redirected you to the homepage'));
            return $this->redirectToRoute('home');
        }
        if (!$event->guestsAllowed) {
            throw $this->createNotFoundException('This event is for members only');
        }

        $user = new User();
        $form = $this
            ->createForm(GuestForm::class, $user)
            ->add('Save', SubmitType::class, ['label' => t('Send me the link')])
        ;

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$limiter->create($request->getClientIp())->consume()->isAccepted()) {
                $this->addFlash('error', t('Too many requests from your network, please try again later.'));
                return $this->redirectToRoute('create_event_guest', ['id' => $event->id]);
            }
            if ($form->get('website')->getData()) {
                return $this->checkEmail($request, (string) $user->email, 'guest');
            }

            $existing = $this->repository->findByEmail($user->email);
            if ($existing && !$existing->guest) {
                // A member: point them to logging in, without telling the visitor
                $this->mailer->send($this->emailFactory->accountExists($existing));
            } else {
                if ($existing === null) {
                    $user->username = sprintf('guest_%s', bin2hex(random_bytes(8)));
                    // Guests log in by link only; this is no usable password
                    $user->password = '!guest';
                    $user->guest = true;
                    $user->locale = Language::fromRequest($request);
                    $this->repository->add($user);
                    $this->commissionMailer->send($this->emailFactory->newUserNotification($user));
                }
                $guest = $existing ?? $user;
                $link = $this->loginLink($guest, 'event_subscription_subscribe', ['id' => $event->id]);
                $this->mailer->send($this->emailFactory->eventGuestSignupEmail($guest, $event, $link));
            }

            return $this->checkEmail($request, $user->email, 'guest', $event);
        }

        return $this->render('user/guest.html.twig', [
            'form' => $form->createView(),
            'event' => $event,
        ]);
    }

    /**
     * Old guest emails linked here with just the user id, which worked as a permanent password.
     * Those links no longer log anyone in.
     */
    #[Route('/loginEventGuest/{user}/{event}', name: 'login_event_guest')]
    public function loginEventGuest(Event $event): Response
    {
        $this->addFlash('error', t('This link is no longer valid. Enter your email again and we will send you a new one.'));

        return $this->redirectToRoute('create_event_guest', ['id' => $event->id]);
    }

    /**
     * A guest who registered (and proved they own the address via the emailed link) picks a
     * username and password. The account then waits for activation like any registration.
     */
    #[Route('/user/upgrade', name: 'user_upgrade')]
    #[IsGranted('ROLE_USER')]
    public function upgradeGuest(
        Request                     $request,
        UserPasswordHasherInterface $userPasswordHasher,
        Security                    $security,
    ): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->guest) {
            return $this->redirectToRoute('user_profile');
        }

        $form = $this->createForm(GuestUpgradeType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user->password = $userPasswordHasher->hashPassword($user, $form->get('plainPassword')->getData());
            $user->guest = false;
            $user->enabled = false;
            $this->repository->add($user);
            $this->cache->delete('pending_activation_count');
            $this->mailer->send($this->emailFactory->signupEmail($user));
            $this->commissionMailer->send($this->emailFactory->newUserNotification($user));

            $security->logout(false);
            $this->addFlash('success', t('Your account has been created. You can log in once the committee has activated it.'));

            return $this->redirectToRoute('login');
        }

        return $this->render('user/upgrade.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/check-email', name: 'check_email')]
    public function checkEmailPage(Request $request): Response
    {
        $context = $request->getSession()->get('check_email');
        if ($context === null) {
            return $this->redirectToRoute('home');
        }

        return $this->render('user/check_email.html.twig', $context);
    }

    private function checkEmail(Request $request, string $email, string $reason, ?Event $event = null): Response
    {
        $request->getSession()->set('check_email', [
            'email' => $email,
            'reason' => $reason,
            'event' => $event ? ['id' => $event->id, 'name' => $event->name] : null,
        ]);

        return $this->redirectToRoute('check_email', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * A signed, expiring login link that lands on the given route.
     */
    private function loginLink(User $user, string $route, array $parameters = []): string
    {
        $url = $this->loginLinkHandler->createLoginLink($user)->getUrl();

        return $url . '&_target_path=' . rawurlencode($this->generateUrl($route, $parameters));
    }
}
