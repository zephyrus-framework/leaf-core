<?php

declare(strict_types=1);

namespace Leaf\Tests\Unit\Content;

use Leaf\Content\MarkdownParser;
use Leaf\Content\SearchIndexBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SearchIndexBuilderTest extends TestCase
{
    private string $fixtureDir;
    private MarkdownParser $parser;

    protected function setUp(): void
    {
        $this->fixtureDir = sys_get_temp_dir() . '/leaf-test-search-' . uniqid();
        mkdir($this->fixtureDir . '/getting-started', 0755, true);

        file_put_contents($this->fixtureDir . '/getting-started/introduction.md', <<<'MD'
---
title: Introduction
order: 1
---

# Introduction

Welcome to the documentation. This is the first page.

## Getting Started

Follow these steps to get started.
MD);

        file_put_contents($this->fixtureDir . '/getting-started/installation.md', <<<'MD'
---
title: Installation
order: 2
---

# Installation

Run composer require to install.
MD);

        $this->parser = new MarkdownParser();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixtureDir);
    }

    #[Test]
    public function buildReturnsIndexEntries(): void
    {
        $builder = new SearchIndexBuilder($this->fixtureDir, $this->parser);
        $index = $builder->build();

        $this->assertCount(2, $index);
    }

    #[Test]
    public function tableCellsAreSeparatedInTheSearchText(): void
    {
        file_put_contents(
            $this->fixtureDir . '/getting-started/table.md',
            "---\ntitle: Table\n---\n\n<table><tr><td>Home page</td><td>Not found page</td></tr></table>\n",
        );

        $entry = $this->entryTitled('Table');

        $this->assertSame('Home page Not found page', $entry['content']);
    }

    #[Test]
    public function aWordSplitByInlineMarkupStaysWhole(): void
    {
        file_put_contents($this->fixtureDir . '/getting-started/inline.md', <<<'MD'
---
title: Inline
---

A wor*d* and a **bo**ld `co`de.
MD);

        $entry = $this->entryTitled('Inline');

        $this->assertSame('A word and a bold code.', $entry['content']);
    }

    #[Test]
    public function entryContainsExpectedFields(): void
    {
        $builder = new SearchIndexBuilder($this->fixtureDir, $this->parser);
        $index = $builder->build();

        $titles = array_column($index, 'title');
        $introIndex = array_search('Introduction', $titles, true);

        $this->assertNotFalse($introIndex);
        $entry = $index[$introIndex];

        $this->assertSame('Introduction', $entry['title']);
        $this->assertSame('getting-started', $entry['section']);
        $this->assertSame('/getting-started/introduction', $entry['url']);
        $this->assertNotEmpty($entry['excerpt']);
        $this->assertIsArray($entry['headings']);
    }

    #[Test]
    public function aNonDefaultLocaleIndexPrefixesTheUrlsWithTheLocale(): void
    {
        $builder = new SearchIndexBuilder($this->fixtureDir, $this->parser, 'https://example.com/');
        $builder->setLocale('fr', 'en', ['en', 'fr']);

        $urls = array_column($builder->build(), 'url');
        sort($urls);

        $this->assertSame([
            'https://example.com/fr/getting-started/installation',
            'https://example.com/fr/getting-started/introduction',
        ], $urls);
    }

    #[Test]
    public function theDefaultLocaleIndexKeepsUnprefixedUrls(): void
    {
        $builder = new SearchIndexBuilder($this->fixtureDir, $this->parser);
        $builder->setLocale('en', 'en', ['en', 'fr']);

        $urls = array_column($builder->build(), 'url');
        sort($urls);

        $this->assertSame(['/getting-started/installation', '/getting-started/introduction'], $urls);
    }

    #[Test]
    public function excerptIsTruncated(): void
    {
        $longContent = "---\ntitle: Long\norder: 1\n---\n\n# Long\n\n" . str_repeat('Lorem ipsum dolor sit amet. ', 50);
        file_put_contents($this->fixtureDir . '/getting-started/long.md', $longContent);

        $builder = new SearchIndexBuilder($this->fixtureDir, $this->parser);
        $index = $builder->build();

        $titles = array_column($index, 'title');
        $longIndex = array_search('Long', $titles, true);
        $entry = $index[$longIndex];

        $this->assertLessThanOrEqual(301, mb_strlen($entry['excerpt'])); // 300 + "…"
    }

    #[Test]
    public function aTruncatedExcerptEndsWithAnEllipsisCharacter(): void
    {
        file_put_contents($this->fixtureDir . '/getting-started/long.md', "# Long\n\n" . str_repeat('filler ', 80));

        $entry = $this->entryTitled('Long');

        $this->assertStringEndsWith('…', $entry['excerpt']);
        $this->assertStringEndsNotWith('...', $entry['excerpt']);
    }

    #[Test]
    public function headingsAreExtracted(): void
    {
        $builder = new SearchIndexBuilder($this->fixtureDir, $this->parser);
        $index = $builder->build();

        $titles = array_column($index, 'title');
        $introIndex = array_search('Introduction', $titles, true);
        $entry = $index[$introIndex];

        $this->assertContains('Getting Started', $entry['headings']);
    }

    #[Test]
    public function returnsEmptyForNonExistentDirectory(): void
    {
        $builder = new SearchIndexBuilder('/nonexistent/path', $this->parser);
        $index = $builder->build();

        $this->assertSame([], $index);
    }

    #[Test]
    public function returnsEmptyForEmptyDirectory(): void
    {
        $emptyDir = sys_get_temp_dir() . '/leaf-test-empty-search-' . uniqid();
        mkdir($emptyDir, 0755, true);

        $builder = new SearchIndexBuilder($emptyDir, $this->parser);
        $index = $builder->build();

        $this->assertSame([], $index);

        rmdir($emptyDir);
    }

    #[Test]
    public function aTitleDerivedFromAnUnderscoredFileNameReadsUnderscoresAsSpaces(): void
    {
        file_put_contents($this->fixtureDir . '/getting-started/second_note.md', 'No front matter.');

        $index = (new SearchIndexBuilder($this->fixtureDir, $this->parser))->build();

        $this->assertContains('Second Note', array_column($index, 'title'));
    }

    #[Test]
    public function aNumericFolderAndFileNameAreIndexedAsStrings(): void
    {
        mkdir($this->fixtureDir . '/2024');
        file_put_contents($this->fixtureDir . '/2024/1.md', 'No front matter.');

        $index = (new SearchIndexBuilder($this->fixtureDir, $this->parser))->build();

        $this->assertContains(['2024', '/2024/1'], array_map(fn (array $e) => [$e['section'], $e['url']], $index));
    }

    #[Test]
    public function aWordPastTheExcerptIsFoundInTheContentField(): void
    {
        file_put_contents($this->fixtureDir . '/getting-started/long.md', "# Long\n\n" . str_repeat('filler ', 80) . "\n\nneedleword here.\n");

        $entry = $this->entryTitled('Long');

        $this->assertStringNotContainsString('needleword', $entry['excerpt']);
        $this->assertStringContainsString('needleword here.', $entry['content']);
        $this->assertStringNotContainsString("\n", $entry['content']);
    }

    #[Test]
    public function anAuthorTypedEntityInTheTitleAndHeadingsIsKeptAsTyped(): void
    {
        file_put_contents($this->fixtureDir . '/getting-started/chips.md', "---\ntitle: Fish &amp; Chips\n---\n\n## Q &amp;amp; A\n\nBody.\n");

        $index = (new SearchIndexBuilder($this->fixtureDir, $this->parser))->build();
        $entry = array_values(array_filter($index, fn (array $e) => str_ends_with($e['url'], '/getting-started/chips')))[0];

        $this->assertSame('Fish &amp; Chips', $entry['title']);
        $this->assertSame(['Q &amp; A'], $entry['headings']);
    }

    #[Test]
    public function escapedMarkupInTheTextStaysTextInContentAndExcerpt(): void
    {
        file_put_contents($this->fixtureDir . '/getting-started/tags.md', "# Tags\n\nUse `<b>` for bold & more.\n");

        $entry = $this->entryTitled('Tags');

        $this->assertStringContainsString('Use <b> for bold & more.', $entry['content']);
        $this->assertStringContainsString('Use <b> for bold & more.', $entry['excerpt']);
    }

    #[Test]
    public function headingPermalinksAreNotPartOfTheContentNorTheExcerpt(): void
    {
        file_put_contents($this->fixtureDir . '/getting-started/links.md', "# Links\n\nIntro.\n\n## More\n\nBody.\n");

        $entry = $this->entryTitled('Links');

        $this->assertSame('Intro. More Body.', $entry['content']);
        $this->assertSame('Intro. More Body.', $entry['excerpt']);
    }

    #[Test]
    public function aLeadingH1IsLeftOutOfTheContentAndTheExcerpt(): void
    {
        file_put_contents($this->fixtureDir . '/getting-started/lead.md', "# Lead\n\nIntro.\n\n# Again\n\nBody.\n");

        $entry = $this->entryTitled('Lead');

        $this->assertSame('Intro. Again Body.', $entry['content']);
        $this->assertSame('Intro. Again Body.', $entry['excerpt']);
    }

    #[Test]
    public function hostileRawHtmlIsIndexedInBoundedTimeWithoutThePcreJit(): void
    {
        $jit = ini_get('pcre.jit');
        ini_set('pcre.jit', '0');
        try {
            file_put_contents(
                $this->fixtureDir . '/getting-started/hostile.md',
                "---\ntitle: Hostile\n---\n\n<div>" . str_repeat('<br', 2_000_000) . "</div>\n\n"
                . '<div>' . str_repeat('<a class="heading-permalink"', 200_000) . "</div>\n",
            );

            $started = hrtime(true);
            $this->entryTitled('Hostile');
            $seconds = (hrtime(true) - $started) / 1e9;
        } finally {
            ini_set('pcre.jit', $jit === false ? '1' : $jit);
        }

        // Parsing the input alone takes about a second; the unbounded patterns added tens of seconds.
        $this->assertLessThan(5.0, $seconds);
    }

    /**
     * @return array<string, mixed>
     */
    private function entryTitled(string $title): array
    {
        $index = (new SearchIndexBuilder($this->fixtureDir, $this->parser))->build();
        $found = array_values(array_filter($index, fn (array $e) => $e['title'] === $title));
        $this->assertCount(1, $found);

        return $found[0];
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
