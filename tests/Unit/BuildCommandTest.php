<?php

declare(strict_types=1);

namespace Leaf\Tests\Unit;

use Leaf\BuildCommand;
use Leaf\Kernel;
use Leaf\Tests\Unit\Fixtures\PageController;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Zephyrus\Routing\Router;

final class BuildCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/leaf-test-buildcmd-' . uniqid();
        mkdir($this->root . '/public', 0755, true);
        define('ROOT_DIR', $this->root);
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
    }

    /**
     * @param list<string> $excludes
     */
    private function build(string $localization, array $excludes = []): string
    {
        file_put_contents($this->root . '/config.yml', $localization);
        $kernel = new class () extends Kernel {
            protected function registerControllers(Router $router): Router
            {
                return $router
                    ->get('/guides/{page}', PageController::class . '@page')
                    ->get('/{page}', PageController::class . '@page');
            }
        };
        $command = new BuildCommand($kernel);
        $command->addPaths(['/404']);
        $command->excludePatterns($excludes);

        ob_start();
        try {
            self::assertSame(0, $command->run());
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }

    #[Test]
    #[RunInSeparateProcess]
    public function everyLocaleOfAMultilingualSiteGetsItsOwnNotFoundFile(): void
    {
        $output = $this->build("localization:\n  locale: en\n  supported_locales: [en, fr]\n");

        self::assertFileExists($this->root . '/dist/404.html');
        self::assertFileExists($this->root . '/dist/fr/404.html');
        self::assertDirectoryDoesNotExist($this->root . '/dist/404');
        self::assertDirectoryDoesNotExist($this->root . '/dist/fr/404');
        self::assertStringContainsString("  -> 404.html created\n", $output);
        self::assertStringContainsString("  -> [fr] 404.html created\n", $output);
    }

    #[Test]
    #[RunInSeparateProcess]
    public function aSingleLanguageSiteKeepsOneNotFoundFileAtTheRoot(): void
    {
        $this->build("localization:\n  locale: en\n  supported_locales: [en]\n");

        self::assertFileExists($this->root . '/dist/404.html');
        self::assertDirectoryDoesNotExist($this->root . '/dist/404');
    }

    /**
     * @return list<string>
     */
    private function indexedUrls(string $searchFile): array
    {
        $index = json_decode((string) file_get_contents($searchFile), true, 512, \JSON_THROW_ON_ERROR);

        return array_column($index, 'url');
    }

    private function writeGuides(string $contentRoot): void
    {
        mkdir($contentRoot . '/guides', 0755, true);
        file_put_contents($contentRoot . '/guides/kept.md', "# Kept\n\nPublic text.\n");
        file_put_contents($contentRoot . '/guides/evil.md', "# Evil\n\nDraft text.\n");
    }

    #[Test]
    #[RunInSeparateProcess]
    public function anExcludedPageIsAbsentFromTheSearchIndexAndItsNeighbourStays(): void
    {
        $this->writeGuides($this->root . '/content');

        $this->build("localization:\n  locale: en\n  supported_locales: [en]\n", ['#^/guides/evil#']);

        self::assertDirectoryDoesNotExist($this->root . '/dist/guides/evil');
        self::assertSame(['/guides/kept'], $this->indexedUrls($this->root . '/dist/search.json'));
    }

    #[Test]
    #[RunInSeparateProcess]
    public function anExcludedPageIsAbsentFromEveryLocalesSearchIndexOfAMultilingualSite(): void
    {
        $this->writeGuides($this->root . '/content');
        $this->writeGuides($this->root . '/content/fr');

        $this->build("localization:\n  locale: en\n  supported_locales: [en, fr]\n", ['#^/guides/evil#']);

        self::assertDirectoryDoesNotExist($this->root . '/dist/fr/guides/evil');
        self::assertSame(['/fr/guides/kept'], $this->indexedUrls($this->root . '/dist/fr/search.json'));
        self::assertSame(['/guides/kept'], $this->indexedUrls($this->root . '/dist/search.json'));
    }
}
