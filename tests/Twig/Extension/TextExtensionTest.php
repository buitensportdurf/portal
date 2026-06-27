<?php

namespace App\Tests\Twig\Extension;

use App\Twig\Extension\TextExtension;
use PHPUnit\Framework\TestCase;

class TextExtensionTest extends TestCase
{
    private TextExtension $ext;

    protected function setUp(): void
    {
        $this->ext = new TextExtension();
    }

    public function testRegistersTruncateFilter(): void
    {
        $names = array_map(fn ($f) => $f->getName(), $this->ext->getFilters());

        self::assertSame(['truncate'], $names);
    }

    public function testReturnsTextUnchangedWhenShorterThanLimit(): void
    {
        self::assertSame('short text', $this->ext->truncate('short text', 30));
    }

    public function testTruncatesAtWordBoundaryAndAppendsSeparator(): void
    {
        $result = $this->ext->truncate('the quick brown fox jumps over the lazy dog', 20);

        self::assertSame('the quick brown fox ...', $result);
    }

    public function testCustomSeparator(): void
    {
        $result = $this->ext->truncate('the quick brown fox jumps over the lazy dog', 20, '…');

        self::assertSame('the quick brown fox …', $result);
    }

    public function testReturnsNullForNull(): void
    {
        self::assertNull($this->ext->truncate(null));
    }

    public function testReturnsEmptyStringUnchanged(): void
    {
        self::assertSame('', $this->ext->truncate(''));
    }
}
