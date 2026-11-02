<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\Node;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\ContentModel;
use Webware\AsciidocPhp\Document;
use Webware\AsciidocPhp\Node\Block;
use Webware\AsciidocPhp\Substitutor\LinkMacros;

/**
 * Inline `link:` macro rendering and bare-URL autolinking, exercised through the
 * real substitution pipeline on a SIMPLE block.
 */
#[CoversClass(Block::class)]
#[CoversMethod(Block::class, 'content')]
#[CoversClass(LinkMacros::class)]
final class SubstitutionLinkTest extends TestCase
{
    private Document $doc;

    // ── Bare-URL autolinking ──────────────────────────────────────────────────

    public function testBareUrlAtEndOfSentenceKeepsTrailingPeriod(): void
    {
        self::assertSame(
            'See <a href="https://example.com/docs">https://example.com/docs</a>.',
            $this->paragraph('See https://example.com/docs.'),
        );
    }

    public function testBareUrlBecomesAnchor(): void
    {
        self::assertSame(
            'See <a href="https://example.com">https://example.com</a> for details.',
            $this->paragraph('See https://example.com for details.'),
        );
    }

    public function testBareUrlInCodeSpanStaysLiteral(): void
    {
        self::assertSame(
            '<code>https://example.com</code>',
            $this->paragraph('`https://example.com`'),
        );
    }

    public function testBareUrlInListingBlockStaysLiteral(): void
    {
        $block = new Block($this->doc, null, 'listing', ContentModel::VERBATIM);
        $block->setLines(['https://example.com']);

        self::assertSame('https://example.com', $block->content());
    }

    public function testBareUrlShorthandWithLabelStaysLiteral(): void
    {
        self::assertSame(
            'https://example.com[Example]',
            $this->paragraph('https://example.com[Example]'),
        );
    }

    // ── link: macro ───────────────────────────────────────────────────────────

    public function testLinkMacroDropsUnknownAttributes(): void
    {
        self::assertSame(
            '<a href="page.adoc">Page</a>',
            $this->paragraph('link:page.adoc[Page,role=external]'),
        );
    }

    public function testLinkMacroEscapesAmpersandOnce(): void
    {
        $html = $this->paragraph('link:https://example.com/?a=1&b=2[Query]');

        self::assertSame(
            '<a href="https://example.com/?a=1&amp;b=2">Query</a>',
            $html,
        );
        self::assertStringNotContainsString('&amp;amp;', $html);
    }

    public function testLinkMacroKeepsRelativeAdocTarget(): void
    {
        self::assertSame(
            '<a href="guide/page.adoc">Page</a>',
            $this->paragraph('link:guide/page.adoc[Page]'),
        );
    }

    public function testLinkMacroOutputIsNotAutolinked(): void
    {
        $html = $this->paragraph('link:https://example.com[Example]');

        self::assertSame('<a href="https://example.com">Example</a>', $html);
        self::assertSame(1, substr_count($html, needle: '<a '));
    }

    public function testLinkMacroWithBareUrlTarget(): void
    {
        self::assertSame(
            '<a href="https://example.com">https://example.com</a>',
            $this->paragraph('link:https://example.com[]'),
        );
    }

    public function testLinkMacroWithBlankWindow(): void
    {
        self::assertSame(
            '<a href="https://example.com" target="_blank" rel="noopener">Example</a>',
            $this->paragraph('link:https://example.com[Example,window=_blank]'),
        );
    }

    public function testLinkMacroWithEmptyLabelUsesTarget(): void
    {
        self::assertSame(
            '<a href="page.adoc">page.adoc</a>',
            $this->paragraph('link:page.adoc[]'),
        );
    }

    public function testLinkMacroWithLabel(): void
    {
        self::assertSame(
            '<a href="https://example.com">Example</a>',
            $this->paragraph('link:https://example.com[Example]'),
        );
    }

    public function testLinkMacroWithNonBlankWindowHasNoRel(): void
    {
        self::assertSame(
            '<a href="page.adoc" target="_self">Page</a>',
            $this->paragraph('link:page.adoc[Page,window=_self]'),
        );
    }

    // ── Unchanged behaviour ───────────────────────────────────────────────────

    public function testXrefMacroStillEmitsFragmentHref(): void
    {
        self::assertSame(
            '<a href="#guide/page.adoc">Page</a>',
            $this->paragraph('xref:guide/page.adoc[Page]'),
        );
    }

    protected function setUp(): void
    {
        $this->doc = new Document();
    }

    private function paragraph(string $line): string
    {
        $block = new Block($this->doc, null, 'paragraph', ContentModel::SIMPLE);
        $block->setLines([$line]);

        return $block->content();
    }
}
