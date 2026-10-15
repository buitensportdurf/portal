<?php

namespace App\Tests\Controller\Event;

use App\Controller\Event\EventView;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class EventViewTest extends TestCase
{
    public function testDefaultsToCards(): void
    {
        self::assertSame(EventView::Cards, EventView::fromRequest(new Request()));
        self::assertNull(EventView::remember(new Request()));
    }

    public function testUrlWinsOverTheRememberedView(): void
    {
        $request = new Request(['view' => 'list'], cookies: [EventView::COOKIE => 'cards']);

        self::assertSame(EventView::List, EventView::fromRequest($request));
        self::assertSame('list', EventView::remember($request)?->getValue());
    }

    public function testUsesTheRememberedView(): void
    {
        $request = new Request(cookies: [EventView::COOKIE => 'list']);

        self::assertSame(EventView::List, EventView::fromRequest($request));
        self::assertNull(EventView::remember($request), 'Nothing new to remember');
    }

    public function testIgnoresUnknownViews(): void
    {
        $request = new Request(['view' => 'nonsense'], cookies: [EventView::COOKIE => 'other']);

        self::assertSame(EventView::Cards, EventView::fromRequest($request));
        self::assertNull(EventView::remember($request));
    }

    public function testEveryViewHasATemplate(): void
    {
        foreach (EventView::cases() as $view) {
            self::assertFileExists(dirname(__DIR__, 3) . '/templates/' . $view->template());
        }
    }
}
