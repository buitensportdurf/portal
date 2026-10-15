<?php

namespace App\Entity;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Intl\Locales;

/**
 * The site's languages. Must match framework.enabled_locales and default_locale in
 * translation.yaml, which a test checks.
 */
enum Language: string
{
    case En = 'en';
    case Nl = 'nl';

    public const self DEFAULT = self::En;

    /**
     * The language the request is shown in, which the LocaleListener already limited to ours.
     */
    public static function fromRequest(Request $request): self
    {
        return self::tryFrom($request->getLocale()) ?? self::DEFAULT;
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The name in the language itself, e.g. "Nederlands".
     */
    public function label(): string
    {
        return ucfirst(Locales::getName($this->value, $this->value));
    }
}
