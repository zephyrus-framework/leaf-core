<?php

declare(strict_types=1);

namespace Leaf\Tests\Unit\Kernel;

use Leaf\Kernel;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Zephyrus\Core\Config\ConfigurationException;

final class KernelLocaleTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/leaf-test-kernel-' . uniqid();
        mkdir($this->root . '/site/locale/en', 0755, true);
        mkdir($this->root . '/site/locale/fr-ca', 0755, true);
        mkdir($this->root . '/site/content/fr-ca', 0755, true);
        file_put_contents($this->root . '/site/locale/en/strings.json', '{"hi":"Hello"}');
        file_put_contents($this->root . '/site/locale/fr-ca/strings.json', '{"hi":"Salut"}');
        file_put_contents($this->root . '/site/content/fr-ca/only.md', "---\ntitle: Seulement\n---\nBonjour\n");
        mkdir($this->root . '/site/app/Views', 0755, true);
        file_put_contents($this->root . '/site/app/Views/prefix.latte', '[{$localePrefix}]');
        define('ROOT_DIR', $this->root . '/site');
    }

    protected function tearDown(): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
        unset($_SERVER['LEAF_LOCALE'], $_SERVER['LEAF_ORIGINAL_URI']);
    }

    private function writeConfigWithoutLocalePath(): void
    {
        file_put_contents(ROOT_DIR . '/config.yml', "localization:\n  locale: en\n  supported_locales: [en]\n");
    }

    private function writeConfig(string $supported, string $locale = 'en'): void
    {
        file_put_contents(ROOT_DIR . '/config.yml', "localization:\n  locale: {$locale}\n  supported_locales: {$supported}\n  locale_path: locale\n");
    }

    private function kernel(): Kernel
    {
        return new class () extends Kernel {
        };
    }

    private function selectDevLocale(Kernel $kernel): void
    {
        (new \ReflectionMethod(Kernel::class, 'applyDevLocale'))->invoke($kernel);
    }

    #[Test]
    #[RunInSeparateProcess]
    public function aSupportedLocaleThatCouldLeaveTheOutputDirectoryRefusesTheBoot(): void
    {
        $this->writeConfig('[en, ../escaped]');

        try {
            $this->kernel();
            self::fail('expected a refusal');
        } catch (ConfigurationException $e) {
            self::assertSame(
                'Configuration section \'localization\' field \'supported_locales\' has invalid value "../escaped": '
                . 'a lowercase language code of 2 or 3 letters, optionally followed by hyphenated subtags of 2 to 8 letters or digits (such as "fr" or "fr-ca").',
                $e->getMessage(),
            );
        }
        self::assertDirectoryDoesNotExist($this->root . '/escaped');
    }

    #[Test]
    #[RunInSeparateProcess]
    public function aDeclaredPrefixSelectsTheContentOfThatLocale(): void
    {
        $this->writeConfig('[en, fr-ca]');
        $_SERVER['LEAF_LOCALE'] = 'fr-ca';
        $_SERVER['LEAF_ORIGINAL_URI'] = '/fr-ca/only';
        $_SERVER['REQUEST_URI'] = '/only';
        $kernel = $this->kernel();

        $this->selectDevLocale($kernel);

        self::assertSame('fr-ca', $kernel->getTranslationExtension()?->getCurrentLocale());
        self::assertSame('fr-ca', $kernel->getContentLoader()->getCurrentLocale());
        self::assertSame('/only', $_SERVER['REQUEST_URI']);
    }

    #[Test]
    #[RunInSeparateProcess]
    public function anUndeclaredPrefixIsPutBackInTheRequestPath(): void
    {
        $this->writeConfig('[en, fr-ca]');
        $_SERVER['LEAF_LOCALE'] = 'ab';
        $_SERVER['LEAF_ORIGINAL_URI'] = '/ab/only';
        $_SERVER['REQUEST_URI'] = '/only';
        $kernel = $this->kernel();

        $this->selectDevLocale($kernel);

        self::assertSame('/ab/only', $_SERVER['REQUEST_URI']);
        self::assertSame('en', $kernel->getTranslationExtension()?->getCurrentLocale());
        self::assertSame('', $kernel->getContentLoader()->getCurrentLocale());
    }

    #[Test]
    #[RunInSeparateProcess]
    public function theDefaultLocalePrefixIsPutBackInTheRequestPath(): void
    {
        $this->writeConfig('[en, fr-ca]');
        $_SERVER['LEAF_LOCALE'] = 'en';
        $_SERVER['LEAF_ORIGINAL_URI'] = '/en/only';
        $_SERVER['REQUEST_URI'] = '/only';
        $kernel = $this->kernel();

        $this->selectDevLocale($kernel);

        self::assertSame('/en/only', $_SERVER['REQUEST_URI']);
    }

    #[Test]
    #[RunInSeparateProcess]
    public function anUnderscoreLocaleIsRefusedWithAHyphenHint(): void
    {
        $this->writeConfig('[en, fr_ca]');

        try {
            $this->kernel();
            self::fail('expected a refusal');
        } catch (ConfigurationException $e) {
            self::assertSame(
                'Configuration section \'localization\' field \'supported_locales\' has invalid value "fr_ca": '
                . 'a lowercase language code of 2 or 3 letters, optionally followed by hyphenated subtags of 2 to 8 letters or digits (such as "fr" or "fr-ca"); use a hyphen, such as "fr-ca".',
                $e->getMessage(),
            );
        }
    }

    #[Test]
    #[RunInSeparateProcess]
    public function aDefaultLocaleAbsentFromTheSupportedLocalesRefusesTheBoot(): void
    {
        $this->writeConfig('[fr-ca]');

        try {
            $this->kernel();
            self::fail('expected a refusal');
        } catch (ConfigurationException $e) {
            self::assertSame(
                'Configuration section \'localization\' field \'locale\' has invalid value "en": '
                . 'one of the supported_locales (fr-ca), since the default locale is built at the site root; set locale to one of them, or add "en" to supported_locales.',
                $e->getMessage(),
            );
        }
    }

    #[Test]
    #[RunInSeparateProcess]
    public function aMalformedDefaultLocaleRefusesTheBoot(): void
    {
        $this->writeConfig('[]', '../x');

        try {
            $this->kernel();
            self::fail('expected a refusal');
        } catch (ConfigurationException $e) {
            self::assertSame(
                'Configuration section \'localization\' field \'locale\' has invalid value "../x": '
                . 'a lowercase language code of 2 or 3 letters, optionally followed by hyphenated subtags of 2 to 8 letters or digits (such as "fr" or "fr-ca").',
                $e->getMessage(),
            );
        }
    }

    #[Test]
    #[RunInSeparateProcess]
    public function theKernelRegistersTheTranslationBeforeLeafSoTheLocalePrefixIsSet(): void
    {
        $this->writeConfig('[en, fr-ca]');
        $kernel = $this->kernel();

        self::assertSame('[]', $this->renderPrefix($kernel, []));
        $kernel->getTranslationExtension()?->setCurrentLocale('fr-ca');
        self::assertSame('[/fr-ca]', $this->renderPrefix($kernel, []));
    }

    #[Test]
    #[RunInSeparateProcess]
    public function aLocalePrefixPassedToTheRenderIsKeptInANonDefaultLocale(): void
    {
        $this->writeConfig('[en, fr-ca]');
        $kernel = $this->kernel();
        $kernel->getTranslationExtension()?->setCurrentLocale('fr-ca');

        self::assertSame('[]', $this->renderPrefix($kernel, ['localePrefix' => '']));
        self::assertSame('[/x]', $this->renderPrefix($kernel, ['localePrefix' => '/x']));
    }

    #[Test]
    #[RunInSeparateProcess]
    public function aSiteWithoutALocalePathReadsItsLocaleDirectory(): void
    {
        $this->writeConfigWithoutLocalePath();
        file_put_contents(ROOT_DIR . '/app/Views/hi.latte', '{localize("hi")}');
        $kernel = $this->kernel();

        self::assertSame('Hello', $kernel->getRenderEngine()->render('hi', []));
    }

    #[Test]
    #[RunInSeparateProcess]
    public function aSiteWithoutALocalePathNorALocaleDirectoryHasNoTranslator(): void
    {
        $this->writeConfigWithoutLocalePath();
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(ROOT_DIR . '/locale', \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir(ROOT_DIR . '/locale');
        $kernel = $this->kernel();

        self::assertNull($kernel->getTranslationExtension());
    }

    /**
     * @param array<string, mixed> $args
     */
    private function renderPrefix(Kernel $kernel, array $args): string
    {
        return $kernel->getRenderEngine()->render('prefix', $args);
    }
}
