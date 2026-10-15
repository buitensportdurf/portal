<?php

namespace App\Tests\Entity;

use App\Entity\Language;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LanguageTest extends KernelTestCase
{
    private string $phpLocale;

    protected function setUp(): void
    {
        $this->phpLocale = \Locale::getDefault();
    }

    protected function tearDown(): void
    {
        \Locale::setDefault($this->phpLocale);
        $booted = static::$booted;
        parent::tearDown();
        if ($booted) {
            // FrameworkBundle installs an exception handler when it boots
            restore_exception_handler();
        }
    }

    public function testMatchesTheTranslationConfig(): void
    {
        $container = self::getContainer();

        self::assertSame($container->getParameter('kernel.enabled_locales'), Language::codes());
        self::assertSame($container->getParameter('kernel.default_locale'), Language::DEFAULT->value);
    }

    public function testLabelIsInTheLanguageItself(): void
    {
        self::assertSame('English', Language::En->label());
        self::assertSame('Nederlands', Language::Nl->label());
    }
}
