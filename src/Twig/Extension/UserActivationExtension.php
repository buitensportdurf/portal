<?php

namespace App\Twig\Extension;

use App\Repository\UserRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class UserActivationExtension extends AbstractExtension
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly CacheInterface $cache,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('pending_activation_count', $this->getPendingActivationCount(...)),
        ];
    }

    public function getPendingActivationCount(): int
    {
        return $this->cache->get('pending_activation_count', function (ItemInterface $item): int {
            $item->expiresAfter(300);

            return $this->userRepository->countDisabled();
        });
    }
}
