<?php

namespace App\Controller\Event;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatableMessage;

use function Symfony\Component\Translation\t;

/**
 * The ways to show a list of events. A new view is a case here plus its template in
 * templates/event/event/view/, which gets `events`.
 */
enum EventView: string
{
    case Cards = 'cards';
    case List = 'list';

    public const string COOKIE = 'event_view';

    /**
     * The view asked for in the URL, else the one last picked, else cards.
     */
    public static function fromRequest(Request $request): self
    {
        return self::tryFrom($request->query->getString('view'))
            ?? self::tryFrom($request->cookies->getString(self::COOKIE))
            ?? self::Cards;
    }

    /**
     * Remembers the view when the URL picked it.
     */
    public static function remember(Request $request): ?Cookie
    {
        $view = self::tryFrom($request->query->getString('view'));

        return $view === null ? null : Cookie::create(self::COOKIE, $view->value, '+1 year');
    }

    public function label(): TranslatableMessage
    {
        return match ($this) {
            self::Cards => t('Cards'),
            self::List => t('List'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Cards => 'fas fa-th-large',
            self::List => 'fas fa-list',
        };
    }

    public function template(): string
    {
        return 'event/event/view/' . $this->value . '.html.twig';
    }
}
