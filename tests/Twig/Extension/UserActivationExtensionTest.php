<?php

namespace App\Tests\Twig\Extension;

use App\Repository\UserRepository;
use App\Twig\Extension\UserActivationExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\Cache\CacheInterface;

class UserActivationExtensionTest extends TestCase
{
    public function testRegistersPendingActivationCountFunction(): void
    {
        $ext = new UserActivationExtension(
            $this->createMock(UserRepository::class),
            $this->createMock(CacheInterface::class),
        );

        $names = array_map(fn ($f) => $f->getName(), $ext->getFunctions());

        self::assertSame(['pending_activation_count'], $names);
    }

    public function testReturnsCountFromRepository(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->expects(self::once())->method('countDisabled')->willReturn(7);

        $ext = new UserActivationExtension($repo, new ArrayAdapter());

        self::assertSame(7, $ext->getPendingActivationCount());
    }

    public function testCachesResultAcrossCalls(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->expects(self::once())->method('countDisabled')->willReturn(3);

        $ext = new UserActivationExtension($repo, new ArrayAdapter());

        self::assertSame(3, $ext->getPendingActivationCount());
        self::assertSame(3, $ext->getPendingActivationCount());
    }
}
