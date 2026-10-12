<?php

namespace App\Twig\Extension;

use App\Entity\User;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class EmailSettingsExtension extends AbstractExtension
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UriSigner             $uriSigner,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('email_settings_url', $this->emailSettingsUrl(...)),
        ];
    }

    /**
     * A signed link to someone's email settings that works without logging in, so guests (who have
     * no password) can switch emails off too. It only opens those settings, so it does not expire.
     */
    public function emailSettingsUrl(User $user): string
    {
        return $this->uriSigner->sign(
            $this->urlGenerator->generate('email_settings', ['user' => (string) $user->id], UrlGeneratorInterface::ABSOLUTE_URL),
        );
    }
}
