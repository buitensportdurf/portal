<?php

namespace App\Tests\EventListener;

use App\Entity\User;
use App\EventListener\LocaleListener;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class LocaleListenerTest extends TestCase
{
    private string $phpLocale;

    protected function setUp(): void
    {
        // Request::setLocale() also sets PHP's default locale
        $this->phpLocale = \Locale::getDefault();
    }

    protected function tearDown(): void
    {
        \Locale::setDefault($this->phpLocale);
    }

    private function listener(?EntityManagerInterface $em = null): LocaleListener
    {
        return new LocaleListener(['en', 'nl'], 'en', $em ?? $this->createStub(EntityManagerInterface::class));
    }

    private function request(?string $acceptLanguage = null, ?string $sessionLocale = null): Request
    {
        $request = Request::create('/');
        if ($acceptLanguage !== null) {
            $request->headers->set('Accept-Language', $acceptLanguage);
        }
        $session = new Session(new MockArraySessionStorage());
        if ($sessionLocale !== null) {
            $session->set(LocaleListener::SESSION_KEY, $sessionLocale);
        }
        $request->setSession($session);
        $request->cookies->set($session->getName(), 'id');

        return $request;
    }

    public static function browserLanguages(): iterable
    {
        yield 'none' => [null, 'en'];
        yield 'dutch' => ['nl-NL,nl;q=0.9,en;q=0.8', 'nl'];
        yield 'belgian dutch' => ['nl-BE', 'nl'];
        yield 'english' => ['en-GB,en;q=0.9', 'en'];
        yield 'german falls back' => ['de-DE,de;q=0.9', 'en'];
    }

    #[DataProvider('browserLanguages')]
    public function testUsesTheBrowserLanguage(?string $acceptLanguage, string $expected): void
    {
        $request = $this->request($acceptLanguage);
        $this->listener()->onRequest(new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        self::assertSame($expected, $request->getLocale());
    }

    public function testChosenLanguageWinsOverTheBrowser(): void
    {
        $request = $this->request('nl-NL', 'en');
        $this->listener()->onRequest(new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        self::assertSame('en', $request->getLocale());
    }

    public function testUnknownSessionLanguageIsIgnored(): void
    {
        $request = $this->request('nl-NL', 'fr');
        $this->listener()->onRequest(new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        self::assertSame('nl', $request->getLocale());
    }

    public function testLoginLoadsTheAccountLanguage(): void
    {
        $user = new User();
        $user->locale = 'nl';
        $request = $this->request('en');

        $this->listener()->onLogin($this->loginEvent($user, $request));

        self::assertSame('nl', $request->getSession()->get(LocaleListener::SESSION_KEY));
    }

    public function testFirstLoginStoresTheCurrentLanguage(): void
    {
        $user = new User();
        $request = $this->request();
        $request->setLocale('nl');
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $this->listener($em)->onLogin($this->loginEvent($user, $request));

        self::assertSame('nl', $user->locale);
    }

    private function loginEvent(User $user, Request $request): LoginSuccessEvent
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return new LoginSuccessEvent(
            $this->createStub(AuthenticatorInterface::class),
            new SelfValidatingPassport(new UserBadge('user', fn() => $user)),
            $token,
            $request,
            null,
            'main',
        );
    }
}
