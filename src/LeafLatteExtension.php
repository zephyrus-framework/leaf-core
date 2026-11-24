<?php

declare(strict_types=1);

namespace Leaf;

use Leaf\Config\LeafConfig;
use Latte\Extension;
use Latte\Runtime\Template;

/**
 * Latte extension that injects LeafConfig values as template parameters.
 *
 * Makes the following variables available in every template:
 *
 *   {$leafName}        — Project name
 *   {$leafVersion}     — Project version
 *   {$leafDescription} — Project description
 *   {$leafGithubUrl}   — GitHub repository URL
 *   {$leafAuthor}      — Author name
 *   {$leafAuthorUrl}   — Author website URL
 *   {$leafLicense}     — License identifier
 *   {$localePrefix}    (empty by default, the locale path prefix once a translation extension is registered)
 */
final class LeafLatteExtension extends Extension
{
    /** @var array<string, mixed> */
    private array $globals;

    public function __construct(LeafConfig $config)
    {
        $this->globals = [
            'leafName' => $config->name,
            'leafVersion' => $config->version,
            'leafDescription' => $config->description,
            'leafGithubUrl' => $config->githubUrl,
            'leafAuthor' => $config->author,
            'leafAuthorUrl' => $config->authorUrl,
            'leafLicense' => $config->license,
            'leafBaseUrl' => rtrim($config->baseUrl, '/'),
            'localePrefix' => '',
            'leafProductionUrl' => $config->productionUrl !== '' ? rtrim($config->productionUrl, '/') : '',
        ];
    }

    /**
     * Whether the template's {$localePrefix} is still the empty default, not a value the render supplied.
     */
    public static function hasDefaultLocalePrefix(Template $template): bool
    {
        return isset(self::defaults()[$template]);
    }

    /**
     * @return \WeakMap<Template, true>
     */
    private static function defaults(): \WeakMap
    {
        static $defaults = new \WeakMap();
        return $defaults;
    }

    public function beforeRender(Template $template): void
    {
        // Inject leaf globals into the template's protected $params property
        // so they're accessible as {$leafName} rather than {$this->global->leafName}.
        // Template-specific variables take precedence.
        $existing = $template->getParameters();

        // Use Closure binding to access the protected $params property.
        $setter = \Closure::bind(function (array $globals, array $existing): void {
            foreach ($globals as $key => $value) {
                if (!array_key_exists($key, $existing)) {
                    $this->params[$key] = $value;
                }
            }
        }, $template, Template::class);

        $setter($this->globals, $existing);

        if (!array_key_exists('localePrefix', $existing)) {
            self::defaults()[$template] = true;
        }
    }
}
