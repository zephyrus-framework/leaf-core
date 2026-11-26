<?php

declare(strict_types=1);

namespace Leaf\Tests\Unit\Localization;

use Leaf\Localization\LocaleCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Zephyrus\Core\Config\ConfigurationException;

final class LocaleCodeTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function acceptedLocales(): array
    {
        return [
            'two letters' => ['fr'],
            'three letters' => ['fil'],
            'language and region' => ['fr-ca'],
            'script and region' => ['zh-hant-tw'],
            'numeric region' => ['es-419'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function refusedLocales(): array
    {
        return [
            'parent traversal' => ['../escaped'],
            'host-relative link' => ['/evil.example'],
            'nested path' => ['fr/ca'],
            'one letter' => ['f'],
            'four letters' => ['frfr'],
            'uppercase' => ['FR'],
            'trailing hyphen' => ['fr-'],
            'one character subtag' => ['fr-c'],
            'underscore' => ['fr_ca'],
            'empty' => [''],
            'trailing newline' => ["fr\n"],
        ];
    }

    #[Test]
    #[DataProvider('acceptedLocales')]
    public function aWellFormedLocaleIsAccepted(string $locale): void
    {
        self::assertTrue(LocaleCode::isValid($locale));
        LocaleCode::assertSupportedLocales(['en', $locale]);
    }

    #[Test]
    #[DataProvider('refusedLocales')]
    public function aMalformedLocaleIsNotValid(string $locale): void
    {
        self::assertFalse(LocaleCode::isValid($locale));
    }

    #[Test]
    public function aMalformedSupportedLocaleIsRefusedWithTheRule(): void
    {
        try {
            LocaleCode::assertSupportedLocales(['en', '../escaped']);
            self::fail('expected a refusal');
        } catch (ConfigurationException $e) {
            self::assertSame(
                'Configuration section \'localization\' field \'supported_locales\' has invalid value "../escaped": '
                . 'a lowercase language code of 2 or 3 letters, optionally followed by hyphenated subtags of 2 to 8 letters or digits (such as "fr" or "fr-ca").',
                $e->getMessage(),
            );
        }
    }

    /**
     * @return array<string, array{list<string>, string}>
     */
    public static function repeatedLocales(): array
    {
        return [
            'same code twice' => [['fr', 'fr'], 'fr'],
            'repeated among others' => [['en', 'fr', 'fr', 'de'], 'fr'],
            'regional code twice' => [['en', 'fr-ca', 'fr-ca'], 'fr-ca'],
        ];
    }

    /**
     * @param list<string> $locales
     */
    #[Test]
    #[DataProvider('repeatedLocales')]
    public function aRepeatedSupportedLocaleIsRefused(array $locales, string $repeated): void
    {
        try {
            LocaleCode::assertSupportedLocales($locales);
            self::fail('expected a refusal');
        } catch (ConfigurationException $e) {
            self::assertSame(
                'Configuration section \'localization\' field \'supported_locales\' has invalid value "' . $repeated . '": '
                . 'listed once; locale codes are compared in lower case, so "EN" and "en" are the same locale.',
                $e->getMessage(),
            );
        }
    }

    #[Test]
    public function aMalformedLocaleIsReportedBeforeARepeat(): void
    {
        try {
            LocaleCode::assertSupportedLocales(['en', ' en', 'en']);
            self::fail('expected a refusal');
        } catch (ConfigurationException $e) {
            self::assertSame(
                'Configuration section \'localization\' field \'supported_locales\' has invalid value " en": '
                . 'a lowercase language code of 2 or 3 letters, optionally followed by hyphenated subtags of 2 to 8 letters or digits (such as "fr" or "fr-ca").',
                $e->getMessage(),
            );
        }
    }

    #[Test]
    public function anUnderscoreLocaleGetsAHyphenHint(): void
    {
        try {
            LocaleCode::assertSupportedLocales(['en', 'fr_ca']);
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
    public function aDefaultLocaleOutsideTheSupportedLocalesIsRefused(): void
    {
        LocaleCode::assertDefaultLocale('en', []);
        LocaleCode::assertDefaultLocale('en', ['en', 'fr']);

        try {
            LocaleCode::assertDefaultLocale('de', ['en', 'fr']);
            self::fail('expected a refusal');
        } catch (ConfigurationException $e) {
            self::assertSame(
                'Configuration section \'localization\' field \'locale\' has invalid value "de": '
                . 'one of the supported_locales (en, fr), since the default locale is built at the site root; set locale to one of them, or add "de" to supported_locales.',
                $e->getMessage(),
            );
        }
    }

    #[Test]
    public function anUnderscoreDefaultLocaleGetsAHyphenHint(): void
    {
        try {
            LocaleCode::assertDefaultLocale('fr_ca', []);
            self::fail('expected a refusal');
        } catch (ConfigurationException $e) {
            self::assertSame(
                'Configuration section \'localization\' field \'locale\' has invalid value "fr_ca": '
                . 'a lowercase language code of 2 or 3 letters, optionally followed by hyphenated subtags of 2 to 8 letters or digits (such as "fr" or "fr-ca"); use a hyphen, such as "fr-ca".',
                $e->getMessage(),
            );
        }
    }

    #[Test]
    public function onlyADeclaredNonDefaultLocaleIsAServedPrefix(): void
    {
        self::assertTrue(LocaleCode::isPrefixedLocale('fr-ca', 'en', ['en', 'fr-ca']));
        self::assertFalse(LocaleCode::isPrefixedLocale('en', 'en', ['en', 'fr-ca']));
        self::assertFalse(LocaleCode::isPrefixedLocale('ab', 'en', ['en', 'fr-ca']));
    }
}
