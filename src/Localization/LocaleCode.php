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
                throw ConfigurationException::invalidValue('localization', 'supported_locales', $locale, self::RULE);
            }
        }
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
