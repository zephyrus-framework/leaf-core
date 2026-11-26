<?php

declare(strict_types=1);

namespace Leaf\Tests\Unit\Config;

use Leaf\Config\LeafConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Zephyrus\Core\Config\ConfigurationException;

final class LeafConfigTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $config = LeafConfig::fromArray([]);

        $this->assertSame('My Project', $config->name);
        $this->assertSame('0.1.0', $config->version);
        $this->assertSame('', $config->description);
        $this->assertSame('', $config->githubUrl);
        $this->assertSame('content', $config->contentPath);
        $this->assertSame('dist', $config->outputPath);
        $this->assertSame('', $config->baseUrl);
        $this->assertSame('', $config->author);
        $this->assertSame('', $config->authorUrl);
        $this->assertSame('MIT', $config->license);
        $this->assertSame([], $config->sections);
        $this->assertSame('', $config->logo);
    }

    #[Test]
    public function customValues(): void
    {
        $config = LeafConfig::fromArray([
            'name' => 'Zephyrus Docs',
            'version' => '2.0.0',
            'description' => 'Framework documentation.',
            'github_url' => 'https://github.com/dadajuice/zephyrus2',
            'content_path' => 'docs',
            'output_path' => 'build',
            'base_url' => '/docs',
            'author' => 'David Tucker',
            'author_url' => 'https://example.com',
            'license' => 'Apache-2.0',
            'sections' => [
                'getting-started' => 'Getting Started',
                'guides' => 'Guides',
            ],
        ]);

        $this->assertSame('Zephyrus Docs', $config->name);
        $this->assertSame('2.0.0', $config->version);
        $this->assertSame('Framework documentation.', $config->description);
        $this->assertSame('https://github.com/dadajuice/zephyrus2', $config->githubUrl);
        $this->assertSame('docs', $config->contentPath);
        $this->assertSame('build', $config->outputPath);
        $this->assertSame('/docs', $config->baseUrl);
        $this->assertSame('David Tucker', $config->author);
        $this->assertSame('https://example.com', $config->authorUrl);
        $this->assertSame('Apache-2.0', $config->license);
    }

    #[Test]
    public function sectionsPreserveInsertionOrder(): void
    {
        $config = LeafConfig::fromArray([
            'sections' => [
                'getting-started' => 'Getting Started',
                'core-concepts' => 'Core Concepts',
                'advanced' => 'Advanced',
            ],
        ]);

        $keys = array_keys($config->sections);
        $this->assertSame(['getting-started', 'core-concepts', 'advanced'], $keys);
        $this->assertSame('Getting Started', $config->sections['getting-started']);
        $this->assertSame('Core Concepts', $config->sections['core-concepts']);
        $this->assertSame('Advanced', $config->sections['advanced']);
    }

    #[Test]
    public function sectionsHandlesNonArrayGracefully(): void
    {
        $config = LeafConfig::fromArray([
            'sections' => 'invalid',
        ]);

        $this->assertSame([], $config->sections);
    }

    #[Test]
    public function sectionsHandlesEmptyArray(): void
    {
        $config = LeafConfig::fromArray([
            'sections' => [],
        ]);

        $this->assertSame([], $config->sections);
    }

    #[Test]
    public function theLogoDefaultsToNothingAndAcceptsAPathUnderPublic(): void
    {
        self::assertSame('', LeafConfig::fromArray([])->logo);
        self::assertSame('', LeafConfig::fromArray(['logo' => ''])->logo);
        self::assertSame('/assets/images/logo.png', LeafConfig::fromArray(['logo' => '/assets/images/logo.png'])->logo);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function refusedLogos(): array
    {
        return [
            'relative path' => ['assets/logo.png'],
            'protocol-relative URL' => ['//cdn.example.com/logo.png'],
            'absolute URL' => ['https://example.com/logo.png'],
            'scheme without slashes' => ['javascript:alert(1)'],
            'backslash' => ['/assets\\logo.png'],
            'parent segment' => ['/assets/../secret.png'],
            'trailing parent segment' => ['/assets/..'],
            'space' => ['/assets/my logo.png'],
            'double quote' => ['/assets/"x".png'],
            'single quote' => ["/assets/'x'.png"],
            'newline' => ["/assets/logo.png\nx"],
            'nul byte' => ["/assets/logo\0.png"],
            'delete' => ["/assets/logo\x7f.png"],
            'next line' => ["/assets/logo\u{85}.png"],
        ];
    }

    #[Test]
    #[DataProvider('refusedLogos')]
    public function aLogoThatCouldEscapePublicOrBreakAnAttributeIsRefused(string $logo): void
    {
        try {
            LeafConfig::fromArray(['logo' => $logo]);
            self::fail('expected a refusal');
        } catch (ConfigurationException $e) {
            self::assertStringStartsWith("Configuration section 'leaf' field 'logo' has invalid value ", $e->getMessage());
        }
    }

    #[Test]
    public function theLogoRefusalNamesTheRuleAndNeverEchoesControlBytes(): void
    {
        try {
            LeafConfig::fromArray(['logo' => "/a\x1b[31m.png"]);
            self::fail('expected a refusal');
        } catch (ConfigurationException $e) {
            self::assertSame(
                'Configuration section \'leaf\' field \'logo\' has invalid value "/a?[31m.png": '
                . 'an empty string, or a path under public/ that starts with a single "/" and holds no "..", backslash, space, quote or control character (such as "/assets/images/logo.png").',
                $e->getMessage(),
            );
        }
    }
}
