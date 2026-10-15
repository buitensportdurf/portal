<?php

namespace App\Tests\Entity;

use App\Entity\Language;
use App\Tests\RestoresKernelSideEffects;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LanguageTest extends KernelTestCase
{
    use RestoresKernelSideEffects;

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
