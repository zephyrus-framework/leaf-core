<?php

declare(strict_types=1);

namespace Leaf;

use Leaf\Localization\LocaleCode;
use Zephyrus\Core\Config\ConfigurationException;

/**
 * Development server router for PHP's built-in web server.
 *
 * Handles:
 * - Static file serving from the public directory
 * - Live-reload endpoint (/__dev/reload) that returns a file change hash
 * - Fallthrough to the Zephyrus application for all other requests, where a refused
 *   configuration is answered as a plain-text 500 carrying the refusal message
 *
 * Usage in bin/router.php:
 *
 *   require __DIR__ . '/../vendor/autoload.php';
 *   (new \Leaf\DevRouter(__DIR__ . '/..'))->handle();
 */
final class DevRouter
{
    private readonly FileWatcher $watcher;

    /**
     * @param resource|null $errorStream Where a refused configuration is also written (the process error output by default).
     */
    public function __construct(
        private readonly string $projectRoot,
        private $errorStream = null,
    ) {
        $this->watcher = new FileWatcher([
            $this->projectRoot . '/content',
            $this->projectRoot . '/app/Views',
            $this->projectRoot . '/public/assets/css',
            $this->projectRoot . '/public/assets/js',
            $this->projectRoot . '/src',
        ]);
    }

    /**
     * Route the current request. Returns true if handled, false to let
     * PHP's built-in server serve a static file.
     */
    public function handle(): bool
    {
        if (!defined('DEV_SERVER')) {
            define('DEV_SERVER', true);
        }

        $uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

        // Live-reload endpoint.
        if ($uri === '/__dev/reload') {
            header('Content-Type: application/json');
            header('Cache-Control: no-cache');
            echo json_encode(['hash' => $this->watcher->hash()]);
            return true;
        }

        // A leading locale-shaped segment is stripped; the kernel keeps it only for a declared non-default locale.
        if (preg_match('#^/([^/]+)(/.*)?$#', $uri, $matches) && LocaleCode::isValid($matches[1])) {
            $publicPath = $this->projectRoot . '/public' . $uri;
            if (!is_file($publicPath)) {
                $_SERVER['LEAF_LOCALE'] = $matches[1];
                $_SERVER['LEAF_ORIGINAL_URI'] = $_SERVER['REQUEST_URI'];
                $remainder = $matches[2] ?? '/';
                $_SERVER['REQUEST_URI'] = $remainder;
                $uri = $remainder;
            }
        }

        // Static file from public/.
        $publicPath = $this->projectRoot . '/public' . $uri;
        if ($uri !== '/' && is_file($publicPath)) {
            return false; // Let PHP's built-in server handle it.
        }

        // Fall through to the Zephyrus application.
        try {
            require $this->projectRoot . '/public/index.php';
        } catch (ConfigurationException $e) {
            $this->reportRefusedConfiguration($e);
        }
        return true;
    }

    private function reportRefusedConfiguration(ConfigurationException $e): void
    {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo $e->getMessage() . PHP_EOL;
        $stream = $this->errorStream ?? fopen('php://stderr', 'w');
        if ($stream === false) {
            return;
        }
        fwrite($stream, $e->getMessage() . PHP_EOL);
        if ($this->errorStream === null) {
            fclose($stream);
        }
    }
}
