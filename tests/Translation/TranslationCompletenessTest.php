<?php

namespace App\Tests\Translation;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Every text in the templates and code needs a Dutch translation, and every translation must be
 * valid ICU. Run `bin/console translation:extract nl --force` to add new keys, then translate them.
 */
class TranslationCompletenessTest extends KernelTestCase
{
    private const array DOMAINS = ['messages', 'validators'];

    public function testEveryTextHasADutchTranslation(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var ExtractorInterface $extractor */
        $extractor = $container->get('translation.extractor');
        $found = new MessageCatalogue('nl');
        foreach (['templates', 'src'] as $directory) {
            $extractor->extract(self::$kernel->getProjectDir() . '/' . $directory, $found);
        }

        $dutch = $this->dutchCatalogue();
        $missing = [];
        foreach (self::DOMAINS as $domain) {
            foreach (array_keys($found->all($domain) + $found->all($domain . MessageCatalogue::INTL_DOMAIN_SUFFIX)) as $key) {
                if (!$dutch->has($key, $domain)) {
                    $missing[] = sprintf('[%s] %s', $domain, $key);
                }
            }
        }

        self::assertSame([], $missing, 'Texts without a Dutch translation');
    }

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
            // FrameworkBundle installs its error handlers when it boots
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testDutchTranslationsAreValidIcu(): void
    {
        $files = glob(dirname(__DIR__, 2) . '/translations/*+intl-icu.*.yaml');
        self::assertNotEmpty($files);
        foreach ($files as $file) {
            foreach (Yaml::parseFile($file) ?? [] as $key => $message) {
                self::assertNotNull(
                    \MessageFormatter::create('nl', (string) $message),
                    sprintf('%s: invalid ICU message for "%s"', basename($file), $key),
                );
            }
        }
    }

    private function dutchCatalogue(): MessageCatalogue
    {
        /** @var TranslatorBagInterface $translator */
        $translator = self::getContainer()->get('translator');
        $catalogue = $translator->getCatalogue('nl');
        // Only what is in our own files counts, not the English fallback
        $own = new MessageCatalogue('nl');
        foreach (self::DOMAINS as $domain) {
            foreach ($catalogue->all($domain) as $key => $message) {
                if ($catalogue->defines($key, $domain) || $catalogue->defines($key, $domain . MessageCatalogue::INTL_DOMAIN_SUFFIX)) {
                    $own->set($key, $message, $domain);
                }
            }
        }

        return $own;
    }
}
