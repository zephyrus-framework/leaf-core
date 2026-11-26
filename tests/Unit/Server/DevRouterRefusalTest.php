<?php

declare(strict_types=1);

namespace Leaf\Tests\Unit\Server;

use Leaf\DevRouter;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DevRouterRefusalTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/leaf-test-router-refusal-' . uniqid();
        mkdir($this->root . '/public', 0755, true);
    }

    protected function tearDown(): void
    {
        unlink($this->root . '/public/index.php');
        if (is_file($this->root . '/config.yml')) {
            unlink($this->root . '/config.yml');
        }
        rmdir($this->root . '/public');
        rmdir($this->root);
    }

    /**
     * @param resource|null $errorStream
     */
    private function serve(string $indexBody, $errorStream = null): string
    {
        file_put_contents($this->root . '/public/index.php', "<?php\n" . $indexBody);
        $_SERVER['REQUEST_URI'] = '/guide';

        ob_start();
        try {
            (new DevRouter($this->root, $errorStream))->handle();
        } finally {
            $body = (string) ob_get_clean();
        }

        return $body;
    }

    #[Test]
    #[RunInSeparateProcess]
    public function aRefusedConfigurationIsAnsweredAsPlainTextWithItsMessage(): void
    {
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stderr);
        $body = $this->serve("throw \\Zephyrus\\Core\\Config\\ConfigurationException::invalidValue('leaf', 'logo', 'x', 'a path');", $stderr);

        $message = "Configuration section 'leaf' field 'logo' has invalid value \"x\": a path." . PHP_EOL;
        self::assertSame($message, $body);
        rewind($stderr);
        self::assertSame($message, stream_get_contents($stderr));
        self::assertSame(500, http_response_code());
    }

    #[Test]
    #[RunInSeparateProcess]
    public function aRefusedLocaleInConfigurationIsAnsweredAsPlainTextWithItsMessage(): void
    {
        file_put_contents($this->root . '/config.yml', "localization:\n  locale: en\n  supported_locales: [\"../escaped\"]\n");
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stderr);
        $body = $this->serve("define('ROOT_DIR', " . var_export($this->root, true) . ");\nnew class () extends \\Leaf\\Kernel {};", $stderr);

        $message = 'Configuration section \'localization\' field \'supported_locales\' has invalid value "../escaped": '
            . 'a lowercase language code of 2 or 3 letters, optionally followed by hyphenated subtags of 2 to 8 letters or digits (such as "fr" or "fr-ca").' . PHP_EOL;
        self::assertSame($message, $body);
        rewind($stderr);
        self::assertSame($message, stream_get_contents($stderr));
        self::assertSame(500, http_response_code());
    }

    #[Test]
    #[RunInSeparateProcess]
    public function anyOtherExceptionIsNotCaughtByTheRouter(): void
    {
        try {
            $this->serve("throw new \\RuntimeException('boom');");
            self::fail('expected the exception to propagate');
        } catch (\RuntimeException $e) {
            self::assertSame('boom', $e->getMessage());
        }
    }
}
