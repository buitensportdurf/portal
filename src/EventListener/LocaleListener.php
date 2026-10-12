<?php

namespace App\EventListener;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Picks the language: the one chosen in this session, else the browser's. Logging in loads the
 * account's language into the session, and a first login stores the current one on the account.
 * There is no locale in the URL, so every existing link keeps working.
 */
final readonly class LocaleListener
{
    public const string SESSION_KEY = '_locale';

    /**
     * @param list<string> $locales
     */
    public function __construct(
        #[Autowire(param: 'kernel.enabled_locales')]
        private array  $locales,
        #[Autowire(param: 'kernel.default_locale')]
        private string $defaultLocale,
        private EntityManagerInterface $em,
    ) {}

    // Before Symfony's LocaleListener (16), so the translator gets this locale
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $chosen = $request->hasPreviousSession() ? $request->getSession()->get(self::SESSION_KEY) : null;

        $request->setLocale(in_array($chosen, $this->locales, true)
            ? $chosen
            : $request->getPreferredLanguage([$this->defaultLocale, ...array_diff($this->locales, [$this->defaultLocale])]));
    }

    #[AsEventListener]
    public function onLogin(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }
        $request = $event->getRequest();
        if ($user->locale === null) {
            $user->locale = $request->getLocale();
            $this->em->flush();
        }
        $request->getSession()->set(self::SESSION_KEY, $user->locale);
        $request->setLocale($user->locale);
    }
}
