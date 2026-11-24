<?php

declare(strict_types=1);

namespace Leaf\Tests\Unit\Server;

use Leaf\DevRouter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DevRouterLocaleTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/leaf-test-router-' . uniqid();
        mkdir($this->root . '/public', 0755, true);
        file_put_contents($this->root . '/public/index.php', '<?php $_SERVER["LEAF_REACHED"] = $_SERVER["REQUEST_URI"];');
    }

    protected function tearDown(): void
    {
        unlink($this->root . '/public/index.php');
        rmdir($this->root . '/public');
        rmdir($this->root);
    }

    /**
     * @return array<string, array{string, ?string, string}>
     */
    public static function requests(): array
    {
        return [
            'region locale' => ['/fr-ca/guide', 'fr-ca', '/guide'],
            'two letter locale' => ['/fr/', 'fr', '/'],
            'bare locale' => ['/fr', 'fr', '/'],
            'three letter locale' => ['/fil/guide', 'fil', '/guide'],
            'no locale' => ['/guide', null, '/guide'],
            'uppercase segment' => ['/FR/guide', null, '/FR/guide'],
            'traversal segment' => ['/../guide', null, '/../guide'],
        ];
    }

    #[Test]
    #[RunInSeparateProcess]
    #[DataProvider('requests')]
    public function aLeadingLocaleSegmentIsStrippedAndTheOriginalPathKept(string $uri, ?string $locale, string $path): void
    {
        $_SERVER['REQUEST_URI'] = $uri;
        unset($_SERVER['LEAF_LOCALE'], $_SERVER['LEAF_ORIGINAL_URI']);

        (new DevRouter($this->root))->handle();

        self::assertSame($locale, $_SERVER['LEAF_LOCALE'] ?? null);
        self::assertSame($path, $_SERVER['LEAF_REACHED']);
        self::assertSame($locale === null ? null : $uri, $_SERVER['LEAF_ORIGINAL_URI'] ?? null);
    }
}
