<?php

declare(strict_types=1);

namespace Leaf\Localization;

use Zephyrus\Core\Config\ConfigurationException;

/**
 * The shape a supported locale must have, since Leaf writes it into paths and links.
 */
final class LocaleCode
{
    public const string PATTERN = '/^[a-z]{2,3}(-[a-z0-9]{2,8})*$/D';

    private const string RULE = 'a lowercase language code of 2 or 3 letters, optionally followed by hyphenated subtags of 2 to 8 letters or digits (such as "fr" or "fr-ca")';

    public static function isValid(string $locale): bool
    {
        return preg_match(self::PATTERN, $locale) === 1;
    }

    /**
     * @param list<string> $supportedLocales
     * @throws ConfigurationException
     */
    public static function assertSupportedLocales(array $supportedLocales): void
    {
        foreach ($supportedLocales as $locale) {
            if (!self::isValid($locale)) {
                throw ConfigurationException::invalidValue('localization', 'supported_locales', $locale, self::ruleFor($locale));
            }
        }
    }

    /**
     * The default locale must be well formed and, when locales are declared, one of them.
     *
     * @param list<string> $supportedLocales
     * @throws ConfigurationException
     */
    public static function assertDefaultLocale(string $locale, array $supportedLocales): void
    {
        if (!self::isValid($locale)) {
            throw ConfigurationException::invalidValue('localization', 'locale', $locale, self::ruleFor($locale));
        }
        if ($supportedLocales !== [] && !in_array($locale, $supportedLocales, true)) {
            throw ConfigurationException::invalidValue('localization', 'locale', $locale, 'one of the supported_locales, since the default locale is built at the site root');
        }
    }

    private static function ruleFor(string $locale): string
    {
        return str_contains($locale, '_') ? self::RULE . '; use a hyphen, such as "fr-ca"' : self::RULE;
    }

    /**
     * Whether a dev server URL prefix names a declared locale other than the default one,
     * the only locales a build writes under "/<locale>/".
     *
     * @param list<string> $supportedLocales
     */
    public static function isPrefixedLocale(string $locale, string $defaultLocale, array $supportedLocales): bool
    {
        return $locale !== $defaultLocale && in_array($locale, $supportedLocales, true);
    }
}
