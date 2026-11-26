# Zephyrus Leaf Core

Core library for building static sites with the [Zephyrus Framework](https://github.com/zephyrus-framework/core). Provides the static site generator, content parsing, SEO tools, multi-locale support, and development server.

This is the **library** package. For a ready-to-use project template, see [zephyrus-framework/leaf](https://github.com/zephyrus-framework/leaf).

## Quick Start

The fastest way to start a new Leaf project is with the template:

```bash
composer create-project zephyrus-framework/leaf my-site
cd my-site
composer dev
```

This gives you a working static site with docs, live-reload, and one-command builds out of the box.

## Installation

If you're integrating Leaf Core into an existing Zephyrus project:

```bash
composer require zephyrus-framework/leaf-core:^1.0
```

## Architecture

Leaf follows the same pattern as Zephyrus itself:

| Package | Type | Purpose |
|---------|------|---------|
| `zephyrus-framework/leaf-core` | Library | Reusable classes, updated via `composer update` |
| `zephyrus-framework/leaf` | Project template | Scaffold for new projects, copied on `create-project` |

When features are added to the core, all projects using it get them on `composer update` without manual porting.

## What's Included

### Build Pipeline

- **`Leaf\BuildCommand`** - Standard build orchestrator. Handles page rendering, sitemap/robots generation, search index, and 404 handling. Supports post-build hooks for project-specific steps.
- **`Leaf\StaticSiteBuilder`** - Renders all routes through the Zephyrus application stack and writes static HTML. Supports multi-locale builds with the default locale at root.
- **`Leaf\StaticBuildResult`** - Immutable value object with build stats, errors, and the list of built pages.

### Application Bootstrap

- **`Leaf\Kernel`** - Abstract application bootstrap. Handles config loading, Latte engine setup, extension registration, and controller discovery. Projects extend this and override `createController()` for dependency injection.

### Content

- **`Leaf\Content\ContentLoader`** - Loads and indexes Markdown content from section/slug directory structure. Provides sidebar navigation, page ordering, and prev/next links.
- **`Leaf\Content\MarkdownParser`** - Markdown to HTML via CommonMark with GitHub Flavored Markdown, heading permalinks, front matter, and table of contents extraction.
- **`Leaf\Content\ParsedMarkdown`** - Immutable parsed document with HTML, front matter, and TOC.
- **`Leaf\Content\SearchIndexBuilder`** - Generates a JSON search index from all content files.

### SEO

- **`Leaf\Seo\SitemapGenerator`** - Generates `sitemap.xml` with multi-locale `xhtml:link` hreflang alternates. Default locale URLs are at root (no prefix).
- **`Leaf\Seo\RobotsGenerator`** - Generates `robots.txt` with optional sitemap reference.

### Localization

- **`Leaf\Localization\TranslationLatteExtension`** - Latte extension providing `localize()` / `i18n()` template functions and `$currentLocale`, `$defaultLocale`, `$supportedLocales` and `$localePrefix` (`""` for the default locale, `"/<locale>"` otherwise) template variables.

### Development

- **`Leaf\DevRouter`** - PHP built-in server router with static file serving, locale prefix handling, and live-reload endpoint.
- **`Leaf\FileWatcher`** - Detects file changes for the live-reload system. Internal, not part of the public API.

### Configuration

- **`Leaf\Config\LeafConfig`** - Typed configuration from the `leaf:` section in `config.yml`.
- **`Leaf\LeafLatteExtension`** - Injects Leaf config values as template globals (`$leafName`, `$leafBaseUrl`, `$leafProductionUrl`, etc.). The globals are public; the class is internal.

## Usage

### Extending the Kernel

Every Leaf project needs an `Application` class that extends `Leaf\Kernel`:

```php
use Leaf\Kernel;

final class Application extends Kernel
{
    protected function createController(string $class): object
    {
        // Inject dependencies into specific controllers
        if ($class === DocsController::class) {
            return new DocsController(
                $this->contentLoader,
                $this->searchIndexBuilder,
                $this->leafConfig,
            );
        }
        return new $class();
    }
}
```

The Kernel provides these protected properties for use in `createController()`:

- `$this->contentLoader` - Content loading and navigation
- `$this->searchIndexBuilder` - Search index generation
- `$this->leafConfig` - Leaf configuration
- `$this->markdownParser` - Markdown parser
- `$this->renderEngine` - Latte template engine
- `$this->translator` - Translation service (if localization is configured)
- `$this->translationExtension` - Translation Latte extension

### Build Script

A minimal `bin/build.php`:

```php
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/vendor/autoload.php';

use App\Models\Core\Application;
use Leaf\BuildCommand;

$app = new Application();
$command = new BuildCommand($app);
exit($command->run());
```

For project-specific build steps, use `onPostBuild()` and `addPaths()`:

```php
$command = new BuildCommand($app);

// Add paths for parameterized routes (e.g. blog posts)
$command->addPaths(['/blog', '/blog/my-post', '/blog/another-post']);

// Run custom steps after the standard pipeline
$command->onPostBuild(function ($result, $outputDir) {
    // Generate OG images, optimize assets, etc.
    passthru('node bin/generate-og-images.js');
});

exit($command->run());
```

### Dev Server

A minimal `bin/router.php`:

```php
define('DEV_SERVER', true);
require __DIR__ . '/../vendor/autoload.php';

if ((new \Leaf\DevRouter(__DIR__ . '/..'))->handle() === false) {
    return false;
}
```

### Multi-Locale Configuration

In `config.yml`:

```yaml
localization:
  locale: "en"
  supported_locales:
    - "en"
    - "fr"
  locale_path: "locale"

leaf:
  production_url: "https://example.com"
```

When `locale_path` is not set, Leaf reads `locale/` at the project root if that directory exists. The default locale builds to the site root (`/`), other locales build to `/{locale}/`. Each `supported_locales` entry must be a code such as `fr` or `fr-ca` (it becomes a path and a link): the core lowercases it first (`FR-CA` is admitted), so `en` and `EN` are the same locale and listing one twice is refused, like anything else that is not a code (`fr_CA`, `../x`), at startup. The default `locale` must follow the same rule and, when `supported_locales` is not empty, be one of them. `leaf build` stops with the refusal; under `php -S` the dev server still starts, then every request answers 500 and the refusal shows in the terminal only. The dev server serves `/{locale}/...` and `content/{locale}/` for declared non-default locales only. The `production_url` enables automatic sitemap and robots.txt generation.

## Versioning

Leaf follows [semantic versioning](https://semver.org/) from 1.0.0: nothing public breaks before 2.0.
Public means the `config.yml` keys, the content layout and front matter, the output in `dist/`, the template names and variables, the CLI, and the public API of the classes listed above except those marked `@internal`.
See [Versioning and stability](https://leaf.ophelios.com/getting-started/versioning/) for the full contract.
Release notes are in [CHANGELOG.md](CHANGELOG.md); moving from 0.1.x is covered in [UPGRADE.md](UPGRADE.md).

## Testing

```bash
composer test
composer analyse
```

## License

MIT
