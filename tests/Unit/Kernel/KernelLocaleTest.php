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

    private function writeConfig(string $supported): void
    {
        file_put_contents(ROOT_DIR . '/config.yml', "localization:\n  locale: en\n  supported_locales: {$supported}\n  locale_path: locale\n");
    }

    private function kernel(): Kernel
    {
        return new class () extends Kernel {
            public function selectLocale(): void
            {
                $this->applyDevLocale();
            }
        };
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

        $kernel->selectLocale();

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

        $kernel->selectLocale();

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

        $kernel->selectLocale();

        self::assertSame('/en/only', $_SERVER['REQUEST_URI']);
    }
}
