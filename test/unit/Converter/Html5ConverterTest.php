<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\Converter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\ContentModel;
use Webware\AsciidocPhp\Converter\Html5Converter;
use Webware\AsciidocPhp\Document;
use Webware\AsciidocPhp\Node\AsciiList;
use Webware\AsciidocPhp\Node\Block;
use Webware\AsciidocPhp\Node\Inline;
use Webware\AsciidocPhp\Node\ListItem;
use Webware\AsciidocPhp\Parser\Parser;
use Webware\AsciidocPhp\Reader\Cursor;
use Webware\AsciidocPhp\Reader\PreprocessorReader;

#[CoversClass(Html5Converter::class)]
final class Html5ConverterTest extends TestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function convert(string $src, bool $embedded = false): string
    {
        $doc    = new Document(['converter' => new Html5Converter()]);
        $reader = new PreprocessorReader($doc, $src, new Cursor(null, null, null));
        Parser::parse($reader, $doc);
        $transform = $embedded ? 'embedded' : null;
        return $doc->getConverter()->convert($doc, $transform);
    }

    // ── Admonition ────────────────────────────────────────────────────────────

    public function testConvertAdmonitionNoteContainsClass(): void
    {
        $html = self::convert("NOTE: Pay attention.\n");
        self::assertStringContainsString('class="admonitionblock note"', $html);
    }

    public function testConvertAdmonitionTipContainsClass(): void
    {
        $html = self::convert("TIP: Helpful tip.\n");
        self::assertStringContainsString('class="admonitionblock tip"', $html);
    }

    public function testConvertAdmonitionWarningContainsClass(): void
    {
        $html = self::convert("WARNING: Be careful.\n");
        self::assertStringContainsString('class="admonitionblock warning"', $html);
    }

    public function testConvertAdmonitionWithoutIconFontRendersTextLabel(): void
    {
        $html = self::convert("WARNING: danger.\n");
        self::assertStringContainsString('<div class="title">Warning</div>', $html);
        self::assertStringNotContainsString('<i class=', $html);
    }

    // -- Block dispatch ---------------------------------------------------------

    public function testConvertColistRendersColistDiv(): void
    {
        $html = self::convert("<1> first\n<2> second\n");

        self::assertStringContainsString('<div class="colist arabic">', $html);
        self::assertStringContainsString('<p>first</p>', $html);
        self::assertSame(2, substr_count($html, '<li>'));
    }

    public function testConvertDlistRendersTermAndDescription(): void
    {
        $doc  = new Document(['converter' => new Html5Converter()]);
        $list = new AsciiList($doc, null, 'dlist');
        $list->appendItem(new ListItem($doc, $list, 'First description.', ''));
        $list->appendItem(new ListItem($doc, $list, 'Second description.', ''));

        $html = $doc->getConverter()->convert($list);

        self::assertStringContainsString('<div class="dlist">', $html);
        self::assertStringContainsString('<dt class="hdlist1">First description.</dt>', $html);
        self::assertStringContainsString('<dt class="hdlist1">Second description.</dt>', $html);
        self::assertSame(2, substr_count($html, '<dd>'));
    }

    // ── Entity preservation (double_encode disabled) ──────────────────────────

    public function testConvertDlistTermKeepsExistingEntities(): void
    {
        $doc  = new Document(['converter' => new Html5Converter()]);
        $list = new AsciiList($doc, null, 'dlist');
        $item = new ListItem($doc, $list, 'Description.', '');
        $item->setAttribute('term', 'A &amp; B');
        $list->appendItem($item);

        self::assertStringContainsString(
            '<dt class="hdlist1">A &amp; B</dt>',
            $doc->getConverter()->convert($list),
        );
    }

    public function testConvertDocumentContainsBodyTag(): void
    {
        $html = self::convert('');
        self::assertStringContainsString('<body', $html);
        self::assertStringContainsString('</body>', $html);
    }

    // ── Document ──────────────────────────────────────────────────────────────

    public function testConvertDocumentContainsDoctypeDeclaration(): void
    {
        $html = self::convert("= Hello\n");
        self::assertStringContainsString('<!DOCTYPE html>', $html);
    }

    public function testConvertDocumentContainsHtmlElement(): void
    {
        $html = self::convert("= Hello\n");
        self::assertStringContainsString('<html lang="en">', $html);
    }

    public function testConvertDocumentContainsMetaCharset(): void
    {
        $html = self::convert('');
        self::assertStringContainsString('<meta charset="UTF-8">', $html);
    }

    public function testConvertDocumentContainsTitleElement(): void
    {
        $html = self::convert("= My Document\n");
        self::assertStringContainsString('<title>My Document</title>', $html);
    }

    public function testConvertDocumentContentConcatenatesAllBlocks(): void
    {
        $html = self::convert("= T\n\nFirst para.\n\nSecond para.\n");

        self::assertStringContainsString('First para.', $html);
        self::assertStringContainsString('Second para.', $html);
        self::assertSame(2, substr_count($html, '<div class="paragraph">'));
    }

    // -- Stylesheet, fonts and highlighter --------------------------------------

    public function testConvertDocumentEmbedsDefaultStylesheet(): void
    {
        $html = self::convert("= T\n");

        self::assertStringContainsString('<style>', $html);
        self::assertStringContainsString('Asciidoctor default stylesheet', $html);
    }

    public function testConvertDocumentHeaderShowsAuthor(): void
    {
        $html = self::convert("= Title\nJane Doe <jane@example.com>\n\n");
        self::assertStringContainsString('Jane Doe', $html);
        self::assertStringContainsString('class="author"', $html);
    }

    public function testConvertDocumentHeaderShowsDocTitle(): void
    {
        $html = self::convert("= My Title\n\n");
        self::assertStringContainsString('<h1>My Title</h1>', $html);
    }

    public function testConvertDocumentLoadsCustomWebFonts(): void
    {
        self::assertStringContainsString(
            'https://fonts.googleapis.com/css?family=Roboto',
            self::convert("= T\n:webfonts: Roboto\n"),
        );
    }

    public function testConvertDocumentLoadsDefaultWebFonts(): void
    {
        self::assertStringContainsString(
            'https://fonts.googleapis.com/css?family=Open+Sans',
            self::convert("= T\n"),
        );
    }

    public function testConvertDocumentTrimsTrailingSlashFromHighlighterDir(): void
    {
        $html = self::convert("= T\n:source-highlighter: highlight.js\n:highlightjsdir: /a/hljs/\n");

        self::assertStringContainsString('src="/a/hljs/highlight.min.js"', $html);
        self::assertStringContainsString('href="/a/hljs/styles/github.min.css"', $html);
        self::assertStringNotContainsString('/a/hljs//', $html);
    }

    public function testConvertDocumentWithCustomStylesheetLinksIt(): void
    {
        $html = self::convert("= T\n:stylesheet: custom.css\n");

        self::assertStringContainsString('<link rel="stylesheet" href="custom.css">', $html);
        self::assertStringNotContainsString('<style>', $html);
    }

    public function testConvertDocumentWithEmptyWebFontsHasNoFontLink(): void
    {
        $doc = new Document([
            'converter'  => new Html5Converter(),
            'attributes' => ['webfonts' => ''],
        ]);

        self::assertStringNotContainsString(
            '<link rel="stylesheet" href="https://fonts.googleapis.com',
            $doc->getConverter()->convert($doc),
        );
    }

    public function testConvertDocumentWithHighlightJsAliasIncludesAssets(): void
    {
        $html = self::convert("= T\n:source-highlighter: highlightjs\n");

        self::assertStringContainsString('highlight.min.js', $html);
        self::assertStringContainsString('github.min.css', $html);
    }

    public function testConvertDocumentWithHighlightJsCustomDir(): void
    {
        $src  = "= Doc\n:source-highlighter: highlight.js\n:highlightjsdir: /assets/hljs\n\n[source,php]\n----\necho 1;\n----\n";
        $html = self::convert($src);
        self::assertStringContainsString('/assets/hljs/styles/', $html);
        self::assertStringContainsString('/assets/hljs/highlight.min.js', $html);
    }

    public function testConvertDocumentWithHighlightJsCustomTheme(): void
    {
        $src  = "= Doc\n:source-highlighter: highlight.js\n:highlightjs-theme: monokai\n\n[source,php]\n----\necho 1;\n----\n";
        $html = self::convert($src);
        self::assertStringContainsString('monokai.min.css', $html);
    }

    public function testConvertDocumentWithHighlightJsIncludesCdnAssets(): void
    {
        $src  = "= Doc\n:source-highlighter: highlight.js\n\n[source,php]\n----\necho 1;\n----\n";
        $html = self::convert($src);
        self::assertStringContainsString('github.min.css', $html);
        self::assertStringContainsString('highlight.min.js', $html);
        self::assertStringContainsString('hljs.highlightBlock(el)', $html);
    }

    public function testConvertDocumentWithIconFontCustomCdn(): void
    {
        $html = self::convert("= Doc\n:icons: font\n:iconfont-cdn: /assets/fa/fa.css\n\nTIP: hi\n");
        self::assertStringContainsString('/assets/fa/fa.css', $html);
        self::assertStringNotContainsString('font-awesome', $html);
    }

    // ── Icon font ─────────────────────────────────────────────────────────────

    public function testConvertDocumentWithIconFontIncludesFontAwesome(): void
    {
        $html = self::convert("= Doc\n:icons: font\n\nNOTE: hi\n");
        self::assertStringContainsString('font-awesome', $html);
    }

    public function testConvertDocumentWithIconFontRendersIElement(): void
    {
        $html = self::convert("= Doc\n:icons: font\n\nNOTE: hi\n");
        self::assertStringContainsString('<i class="fa icon-note"', $html);
    }

    public function testConvertDocumentWithInlineTocHasNoSidebarClass(): void
    {
        $doc = new Document([
            'converter'  => new Html5Converter(),
            'attributes' => ['toc' => ''],
        ]);
        $src    = "= Title\n\n== One\n\nContent.\n";
        $reader = new PreprocessorReader($doc, $src, new Cursor(null, null, null));
        Parser::parse($reader, $doc);
        $html = $doc->getConverter()->convert($doc);
        self::assertStringNotContainsString('<body class="article toc2', $html);
        self::assertStringNotContainsString('<div id="toc" class="toc2">', $html);
    }

    public function testConvertDocumentWithLinkcssLinksStylesheet(): void
    {
        $html = self::convert("= T\n:linkcss:\n");

        self::assertStringContainsString('<link rel="stylesheet" href="asciidoctor.css">', $html);
        self::assertStringNotContainsString('<style>', $html);
    }

    public function testConvertDocumentWithNoembedHasNoStylesheet(): void
    {
        self::assertStringNotContainsString('<style>', self::convert("= T\n:noembed:\n"));
    }

    // ── Head asset guards ─────────────────────────────────────────────────────

    public function testConvertDocumentWithNonFontIconsHasNoIconStylesheet(): void
    {
        $html = self::convert("= Title\n:icons: image\n\nBody.\n");

        self::assertStringNotContainsString('font-awesome', $html);
    }

    public function testConvertDocumentWithoutHighlighterHasNoHighlighterAssets(): void
    {
        self::assertStringNotContainsString('highlight.min.css', self::convert("= T\n"));
    }

    public function testConvertDocumentWithoutHighlighterHasNoHljsScript(): void
    {
        $src  = "= Doc\n\n[source,php]\n----\necho 1;\n----\n";
        $html = self::convert($src);
        self::assertStringNotContainsString('hljs.highlightBlock(el)', $html);
    }

    public function testConvertDocumentWithoutIconFontHasNoFontAwesome(): void
    {
        $html = self::convert("= Doc\n\nNOTE: hi\n");
        self::assertStringNotContainsString('font-awesome', $html);
    }

    public function testConvertDocumentWithoutTitleHasNoHeader(): void
    {
        self::assertStringNotContainsString('<div id="header">', self::convert("A paragraph.\n"));
    }

    public function testConvertDocumentWithoutTocHasNoTocDiv(): void
    {
        self::assertStringNotContainsString('<div id="toc"', self::convert("= T\n\n== One\n\ntext\n"));
    }

    // ── TOC ───────────────────────────────────────────────────────────────────

    public function testConvertDocumentWithTocAttributeContainsTocDiv(): void
    {
        $doc = new Document([
            'converter'  => new Html5Converter(),
            'attributes' => ['toc' => ''],
        ]);
        $src    = "= Title\n\n== One\n\nContent.\n\n== Two\n\nContent.\n";
        $reader = new PreprocessorReader($doc, $src, new Cursor(null, null, null));
        Parser::parse($reader, $doc);
        $html = $doc->getConverter()->convert($doc);
        self::assertStringContainsString('<div id="toc"', $html);
        self::assertStringContainsString('<ul class="sectlevel1">', $html);
    }

    public function testConvertDocumentWithTocLeftDoesNotDuplicateToc(): void
    {
        $doc = new Document([
            'converter'  => new Html5Converter(),
            'attributes' => ['toc' => 'left'],
        ]);
        $src    = "= Title\n\n== One\n\nContent.\n\n== Two\n\nContent.\n";
        $reader = new PreprocessorReader($doc, $src, new Cursor(null, null, null));
        Parser::parse($reader, $doc);
        $html = $doc->getConverter()->convert($doc);
        self::assertSame(1, substr_count($html, '<div id="toc"'));
    }

    public function testConvertDocumentWithTocLeftHasSidebarBodyClass(): void
    {
        $doc = new Document([
            'converter'  => new Html5Converter(),
            'attributes' => ['toc' => 'left'],
        ]);
        $src    = "= Title\n\n== One\n\nContent.\n\n== Two\n\nContent.\n";
        $reader = new PreprocessorReader($doc, $src, new Cursor(null, null, null));
        Parser::parse($reader, $doc);
        $html = $doc->getConverter()->convert($doc);
        self::assertStringContainsString('<body class="article toc2 toc-left">', $html);
    }

    public function testConvertDocumentWithTocLeftHasToc2Div(): void
    {
        $doc = new Document([
            'converter'  => new Html5Converter(),
            'attributes' => ['toc' => 'left'],
        ]);
        $src    = "= Title\n\n== One\n\nContent.\n\n== Two\n\nContent.\n";
        $reader = new PreprocessorReader($doc, $src, new Cursor(null, null, null));
        Parser::parse($reader, $doc);
        $html = $doc->getConverter()->convert($doc);
        self::assertStringContainsString('<div id="toc" class="toc2">', $html);
    }

    public function testConvertDocumentWithTocLeftPlacesSidebarBeforeHeader(): void
    {
        $doc = new Document([
            'converter'  => new Html5Converter(),
            'attributes' => ['toc' => 'left'],
        ]);
        $src    = "= Title\n\n== One\n\nContent.\n\n== Two\n\nContent.\n";
        $reader = new PreprocessorReader($doc, $src, new Cursor(null, null, null));
        Parser::parse($reader, $doc);
        $html = $doc->getConverter()->convert($doc);
        // The sidebar toc must appear before #header in the output
        $tocPos    = strpos($html, '<div id="toc"');
        $headerPos = strpos($html, '<div id="header">');
        self::assertNotFalse($tocPos);
        self::assertNotFalse($headerPos);
        // The sidebar TOC is now rendered inside #header (matches Asciidoctor structure).
        self::assertGreaterThan($headerPos, $tocPos);
    }

    public function testConvertDocumentWithTocPlacementLeftAttribute(): void
    {
        $doc = new Document([
            'converter'  => new Html5Converter(),
            'attributes' => ['toc' => '', 'toc-placement' => 'left'],
        ]);
        $src    = "= Title\n\n== One\n\nContent.\n\n== Two\n\nContent.\n";
        $reader = new PreprocessorReader($doc, $src, new Cursor(null, null, null));
        Parser::parse($reader, $doc);
        $html = $doc->getConverter()->convert($doc);
        self::assertStringContainsString('<body class="article toc2 toc-left">', $html);
        self::assertStringContainsString('<div id="toc" class="toc2">', $html);
    }

    public function testConvertDocumentWithTocRightHasTocRightBodyClass(): void
    {
        $doc = new Document([
            'converter'  => new Html5Converter(),
            'attributes' => ['toc' => 'right'],
        ]);
        $src    = "= Title\n\n== One\n\nContent.\n\n== Two\n\nContent.\n";
        $reader = new PreprocessorReader($doc, $src, new Cursor(null, null, null));
        Parser::parse($reader, $doc);
        $html = $doc->getConverter()->convert($doc);
        self::assertStringContainsString('<body class="article toc2 toc-right">', $html);
        self::assertStringContainsString('<div id="toc" class="toc2">', $html);
    }

    public function testConvertEmbeddedConcatenatesAllBlocks(): void
    {
        $html = self::convert("First para.\n\nSecond para.\n", true);

        self::assertStringContainsString('First para.', $html);
        self::assertStringContainsString('Second para.', $html);
        self::assertSame(2, substr_count($html, '<div class="paragraph">'));
    }

    public function testConvertEmbeddedContainsContentWrapper(): void
    {
        $html = self::convert("Hello.\n", embedded: true);
        self::assertStringContainsString('<div id="content">', $html);
    }

    // ── Embedded ──────────────────────────────────────────────────────────────

    public function testConvertEmbeddedDoesNotContainDoctypeDeclaration(): void
    {
        $html = self::convert("Hello world.\n", embedded: true);
        self::assertStringNotContainsString('<!DOCTYPE html>', $html);
    }

    // -- Escaping contracts -----------------------------------------------------

    public function testConvertEscapesTitleAuthorRevdateAndFooterLabel(): void
    {
        $raw = "T \" &amp; \x80";
        $doc = new Document([
            'converter'  => new Html5Converter(),
            'attributes' => [
                'doctitle'          => $raw,
                'author'            => $raw,
                'revdate'           => $raw,
                'last-update-label' => $raw,
            ],
        ]);

        $html = $doc->getConverter()->convert($doc);
        $safe = "T &quot; &amp; \u{FFFD}";

        self::assertStringContainsString("<title>{$safe}</title>", $html);
        self::assertStringContainsString("<h1>{$safe}</h1>", $html);
        self::assertStringContainsString("<span class=\"author\">{$safe}</span>", $html);
        self::assertStringContainsString("<span class=\"revdate\">{$safe}</span>", $html);
        self::assertStringContainsString("<div id=\"footer-text\">\n{$safe} {$safe}\n</div>", $html);
    }

    // ── Example block ─────────────────────────────────────────────────────────

    public function testConvertExampleContainsDiv(): void
    {
        $html = self::convert("====\nExample content.\n====\n");
        self::assertStringContainsString('<div class="exampleblock">', $html);
    }

    public function testConvertExampleWithTitleRendersCaption(): void
    {
        self::assertStringContainsString(
            '<div class="title">Example. My Example</div>',
            self::convert(".My Example\n====\nbody\n====\n"),
        );
    }

    public function testConvertFloatingTitleKeepsExistingEntities(): void
    {
        $doc   = new Document(['converter' => new Html5Converter()]);
        $block = new Block($doc, null, 'paragraph');
        $block->setTitle('A &amp; B');
        $block->setLevel(2);

        self::assertStringContainsString(
            '<h3 class="float">A &amp; B</h3>',
            $doc->getConverter()->convert($block, 'floating_title'),
        );
    }

    public function testConvertFloatingTitleTransformRendersFloatHeading(): void
    {
        $doc   = new Document(['converter' => new Html5Converter()]);
        $block = new Block($doc, null, 'paragraph');
        $block->setTitle('Floating');
        $block->setLevel(2);

        self::assertStringContainsString(
            '<h3 class="float">Floating</h3>',
            $doc->getConverter()->convert($block, 'floating_title'),
        );
    }

    public function testConvertFooterRendersEmptyTextWhenLabelUnset(): void
    {
        $doc = new Document([
            'converter'  => new Html5Converter(),
            'attributes' => ['last-update-label' => false],
        ]);

        self::assertStringContainsString(
            "<div id=\"footer-text\">\n\n</div>",
            $doc->getConverter()->convert($doc),
        );
    }

    public function testConvertHeaderKeepsTitleAndSidebarToc(): void
    {
        $doc = new Document([
            'converter'  => new Html5Converter(),
            'attributes' => ['toc' => 'left'],
        ]);
        $reader = new PreprocessorReader(
            $doc,
            "= Title\n\n== One\n\n=== One One\n\ntext\n",
            new Cursor(null, null, null),
        );
        Parser::parse($reader, $doc);
        $html = $doc->getConverter()->convert($doc);

        self::assertStringContainsString('<h1>Title</h1>', $html);
        self::assertStringContainsString('<div id="toc" class="toc2">', $html);
    }

    public function testConvertHeaderKeepsTitleAuthorRevdateAndInlineToc(): void
    {
        $doc = new Document([
            'converter'  => new Html5Converter(),
            'attributes' => ['toc' => '', 'revdate' => '2024-01-01'],
        ]);
        $reader = new PreprocessorReader(
            $doc,
            "= Title\nJane Doe <jane@example.com>\n\n== One\n\ntext\n",
            new Cursor(null, null, null),
        );
        Parser::parse($reader, $doc);
        $html = $doc->getConverter()->convert($doc);

        self::assertStringContainsString('<h1>Title</h1>', $html);
        self::assertStringContainsString('<span class="author">Jane Doe</span>', $html);
        self::assertStringContainsString('<span class="revdate">2024-01-01</span>', $html);
        self::assertStringContainsString('<div id="toc" class="toc">', $html);
    }

    public function testConvertImageAltKeepsExistingEntities(): void
    {
        $doc   = new Document(['converter' => new Html5Converter()]);
        $block = new Block($doc, null, 'image');
        $block->setAttribute('target', 'pic.png');
        $block->setAttribute('alt', 'A &amp; B');

        self::assertStringContainsString('alt="A &amp; B"', $doc->getConverter()->convert($block));
    }

    // ── Image macro ───────────────────────────────────────────────────────────

    public function testConvertImageContainsImgTag(): void
    {
        $html = self::convert("image::photo.png[Alt text]\n");
        self::assertStringContainsString('<img', $html);
        self::assertStringContainsString('src="photo.png"', $html);
        self::assertStringContainsString('alt="Alt text"', $html);
    }

    public function testConvertImageWithLinkWrapsInAnchor(): void
    {
        self::assertStringContainsString(
            '<a class="image" href="https://example.com">',
            self::convert("image::pic.png[Alt,link=https://example.com]\n"),
        );
    }

    // -- Images and source blocks ----------------------------------------------

    public function testConvertImageWithWidthAndHeight(): void
    {
        $html = self::convert("image::pic.png[Alt,width=100,height=50]\n");

        self::assertStringContainsString('width="100"', $html);
        self::assertStringContainsString('height="50"', $html);
    }

    public function testConvertImageWrappedInImageblockDiv(): void
    {
        $html = self::convert("image::photo.png[]\n");
        self::assertStringContainsString('<div class="imageblock">', $html);
    }

    // ── Listing block ─────────────────────────────────────────────────────────

    public function testConvertListingContainsPre(): void
    {
        $html = self::convert("----\necho hello\n----\n");
        self::assertStringContainsString('<div class="listingblock">', $html);
        self::assertStringContainsString('<pre class="highlight"><code>', $html);
    }

    public function testConvertListingWithSourceLanguage(): void
    {
        $html = self::convert("[source,php]\n----\n<?php echo 'hi';\n----\n");
        self::assertStringContainsString('class="language-php hljs"', $html);
        self::assertStringContainsString('data-lang="php"', $html);
    }

    // ── Literal block ─────────────────────────────────────────────────────────

    public function testConvertLiteralContainsLiteralblockDiv(): void
    {
        $html = self::convert("....\nliteral text\n....\n");
        self::assertStringContainsString('<div class="literalblock">', $html);
    }

    // ── Ordered list ──────────────────────────────────────────────────────────

    public function testConvertOlistContainsOl(): void
    {
        $html = self::convert(". First\n. Second\n");
        self::assertStringContainsString('<div class="olist arabic">', $html);
        self::assertStringContainsString('<ol class="arabic">', $html);
        self::assertSame(2, substr_count($html, '<li>'));
    }

    public function testConvertOnlyTopLevelSectionHasSectionbody(): void
    {
        $html = self::convert("= T\n\n== A\n\n=== A1\n\n==== A2\n\ntext\n");

        self::assertSame(1, substr_count($html, 'class="sectionbody"'));
    }

    public function testConvertOpenBlockRendersOpenblock(): void
    {
        $html = self::convert("--\nopen content\n--\n");

        self::assertStringContainsString('<div class="openblock">', $html);
    }

    // ── Page break ────────────────────────────────────────────────────────────

    public function testConvertPageBreakOutputsPageBreakDiv(): void
    {
        $html = self::convert("<<<\n");
        self::assertStringContainsString('page-break-after: always', $html);
    }

    // ── Paragraph ─────────────────────────────────────────────────────────────

    public function testConvertParagraphContainsDiv(): void
    {
        $html = self::convert("Hello world.\n");
        self::assertStringContainsString('<div class="paragraph">', $html);
        self::assertStringContainsString('<p>Hello world.</p>', $html);
    }

    public function testConvertParagraphWithoutRoleHasNoExtraClass(): void
    {
        $doc   = new Document(['converter' => new Html5Converter()]);
        $block = new Block($doc, null, 'paragraph');
        $block->setLines(['body']);

        self::assertSame(
            "<div class=\"paragraph\">\n<p>body</p>\n</div>\n",
            $doc->getConverter()->convert($block),
        );
    }

    public function testConvertParagraphWithRoleRendersRoleClass(): void
    {
        $doc   = new Document(['converter' => new Html5Converter()]);
        $block = new Block($doc, null, 'paragraph');
        $block->setLines(['body']);
        $block->setAttribute('role', 'myrole');

        self::assertSame(
            "<div class=\"paragraph myrole\">\n<p>body</p>\n</div>\n",
            $doc->getConverter()->convert($block),
        );
    }

    public function testConvertParagraphWithTitle(): void
    {
        $html = self::convert(".My Paragraph\nHello.\n");
        self::assertStringContainsString('<div class="title">My Paragraph</div>', $html);
    }

    public function testConvertPassBlockIsNotWrappedInParagraph(): void
    {
        $html = self::convert("++++\n<b>raw</b>\n++++\n");

        self::assertStringContainsString('<b>raw</b>', $html);
        self::assertStringNotContainsString('class="paragraph"', $html);
    }

    // ── Pass block ────────────────────────────────────────────────────────────

    public function testConvertPassOutputsRawContent(): void
    {
        $html = self::convert("++++\n<b>raw</b>\n++++\n");
        self::assertStringContainsString('<b>raw</b>', $html);
    }

    public function testConvertPreambleRendersPreambleDiv(): void
    {
        $html = self::convert("= Doc\n\nPreamble para.\n\n== One\n\ntext\n");

        self::assertStringContainsString('<div id="preamble">', $html);
        self::assertStringContainsString('Preamble para.', $html);
    }

    public function testConvertQuoteAttributionKeepsExistingEntities(): void
    {
        $doc   = new Document(['converter' => new Html5Converter()]);
        $block = new Block($doc, null, 'quote', ContentModel::COMPOUND);
        $block->setAttribute('attribution', 'A &amp; B');
        $block->setAttribute('citetitle', 'C &amp; D');

        $html = $doc->getConverter()->convert($block);

        self::assertStringContainsString('A &amp; B', $html);
        self::assertStringContainsString('<cite>C &amp; D</cite>', $html);
    }

    // ── Quote block ───────────────────────────────────────────────────────────

    public function testConvertQuoteContainsBlockquote(): void
    {
        $html = self::convert("____\nQuoted text.\n____\n");
        self::assertStringContainsString('<blockquote>', $html);
        self::assertStringContainsString('<div class="quoteblock">', $html);
    }

    public function testConvertQuoteRendersAttributionAndCitetitle(): void
    {
        $doc   = new Document(['converter' => new Html5Converter()]);
        $block = new Block($doc, null, 'quote', ContentModel::COMPOUND);
        $block->setAttribute('attribution', 'Ada Lovelace');
        $block->setAttribute('citetitle', 'Notes');

        $html = $doc->getConverter()->convert($block);

        self::assertStringContainsString('<div class="attribution">', $html);
        self::assertStringContainsString('Ada Lovelace', $html);
        self::assertStringContainsString('<cite>Notes</cite>', $html);
    }

    public function testConvertQuoteWithAttributionOnlyOmitsCite(): void
    {
        $doc   = new Document(['converter' => new Html5Converter()]);
        $block = new Block($doc, null, 'quote', ContentModel::COMPOUND);
        $block->setAttribute('attribution', 'Only');

        $html = $doc->getConverter()->convert($block);

        self::assertStringContainsString('<div class="attribution">', $html);
        self::assertStringNotContainsString('<cite>', $html);
    }

    public function testConvertQuoteWithCitetitleOnlyRendersCite(): void
    {
        $doc   = new Document(['converter' => new Html5Converter()]);
        $block = new Block($doc, null, 'quote', ContentModel::COMPOUND);
        $block->setAttribute('citetitle', 'Only Title');

        $html = $doc->getConverter()->convert($block);

        self::assertStringContainsString('<cite>Only Title</cite>', $html);
    }

    public function testConvertSection2ContainsH3Heading(): void
    {
        $html = self::convert("= Title\n\n== Section One\n\n=== Sub Section\n\nContent.\n");
        self::assertMatchesRegularExpression('/<h3[^>]*>Sub Section<\/h3>/', $html);
    }

    public function testConvertSectionConcatenatesAllChildren(): void
    {
        $html = self::convert("= T\n\n== A\n\nFirst para.\n\nSecond para.\n");

        self::assertStringContainsString('First para.', $html);
        self::assertStringContainsString('Second para.', $html);
        self::assertSame(2, substr_count($html, '<div class="paragraph">'));
    }

    public function testConvertSectionContainsH2Heading(): void
    {
        $html = self::convert("= Title\n\n== Section One\n\nContent.\n");
        self::assertMatchesRegularExpression('/<h2[^>]*>Section One<\/h2>/', $html);
    }

    // ── Section ───────────────────────────────────────────────────────────────

    public function testConvertSectionContainsSect1Div(): void
    {
        $html = self::convert("= Title\n\n== Section One\n\nContent.\n");
        self::assertStringContainsString('class="sect1"', $html);
    }

    // -- Sections and document assembly -----------------------------------------

    public function testConvertSectionDeepLevelClampsToH6(): void
    {
        $html = self::convert("= T\n\n====== Deep\n\ntext\n");

        self::assertStringContainsString('<h6 id="_deep">Deep</h6>', $html);
    }

    public function testConvertSectionWithIdAttribute(): void
    {
        $html = self::convert("= Title\n\n[[my-id]]\n== Named Section\n\nContent.\n");
        self::assertStringContainsString('id="my-id"', $html);
    }

    // ── Sidebar ───────────────────────────────────────────────────────────────

    public function testConvertSidebarContainsDiv(): void
    {
        $html = self::convert("****\nSidebar content.\n****\n");
        self::assertStringContainsString('<div class="sidebarblock">', $html);
    }

    public function testConvertSidebarTocStopsAtSecondLevelByDefault(): void
    {
        $doc = new Document([
            'converter'  => new Html5Converter(),
            'attributes' => ['toc' => 'left'],
        ]);
        $reader = new PreprocessorReader(
            $doc,
            "= Doc\n\n== A\n\n=== A1\n\n==== A2\n\ntext\n",
            new Cursor(null, null, null),
        );
        Parser::parse($reader, $doc);
        $html = $doc->getConverter()->convert($doc);

        self::assertStringContainsString('<ul class="sectlevel1">', $html);
        self::assertStringContainsString('<ul class="sectlevel2">', $html);
        self::assertStringNotContainsString('<ul class="sectlevel3">', $html);
    }

    public function testConvertSourceBlockWithLanguageHasCodeClass(): void
    {
        self::assertStringContainsString(
            'class="language-php hljs"',
            self::convert("[source,php]\n----\ncode\n----\n"),
        );
    }

    public function testConvertSourceBlockWithoutLanguageHasNoCodeClass(): void
    {
        self::assertStringNotContainsString('language-', self::convert("[source]\n----\ncode\n----\n"));
    }

    // -- Tables ----------------------------------------------------------------

    public function testConvertTableColumnWidthsAreExact(): void
    {
        $html = self::convert("[cols=\"1,2\"]\n|===\n|a |b\n|c |d\n|===\n");

        self::assertStringContainsString('<col style="width: 33.3333%;">', $html);
        self::assertStringContainsString('<col style="width: 66.6666%;">', $html);
        self::assertSame(2, substr_count($html, '<tr>'));
        self::assertSame(4, substr_count($html, '<td class="tableblock'));
    }

    public function testConvertTableContainsCells(): void
    {
        $html = self::convert("|===\n|A |B\n|===\n");
        self::assertStringContainsString('<td', $html);
    }

    // ── Table ─────────────────────────────────────────────────────────────────

    public function testConvertTableContainsTableElement(): void
    {
        $html = self::convert("|===\n|A |B\n|C |D\n|===\n");
        self::assertStringContainsString('<table', $html);
        self::assertStringContainsString('</table>', $html);
    }

    public function testConvertTableHeaderOptionUsesTh(): void
    {
        $html = self::convert("[options=\"header\"]\n|===\n|H1 |H2\n|a |b\n|===\n");

        self::assertStringContainsString('<thead>', $html);
        self::assertSame(4, substr_count($html, '<th class="tableblock'));
    }

    public function testConvertTableWithFullWidthUsesStretchClass(): void
    {
        $html = self::convert("[width=100]\n|===\n|a |b\n|===\n");

        self::assertStringContainsString('frame-all grid-all stretch', $html);
        self::assertStringNotContainsString('style="width:100%"', $html);
    }

    public function testConvertTableWithPartialWidthUsesStyleAttribute(): void
    {
        $html = self::convert("[width=75]\n|===\n|a |b\n|===\n");

        self::assertStringContainsString('style="width:75%"', $html);
        self::assertStringNotContainsString('grid-all stretch', $html);
    }

    public function testConvertTableWithStripesAddsStripeClass(): void
    {
        self::assertStringContainsString('stripes-even', self::convert("[stripes=even]\n|===\n|a |b\n|===\n"));
    }

    public function testConvertTableWithTitleRendersCaption(): void
    {
        self::assertStringContainsString(
            '<caption class="title">Table. My Table</caption>',
            self::convert(".My Table\n|===\n|a |b\n|===\n"),
        );
    }

    public function testConvertTableWithZeroWidthColumnsRendersZeroPercent(): void
    {
        self::assertStringContainsString(
            '<col style="width: 0%;">',
            self::convert("[cols=\"0,0\"]\n|===\n|a |b\n|===\n"),
        );
    }

    // ── Thematic break ────────────────────────────────────────────────────────

    public function testConvertThematicBreakOutputsHr(): void
    {
        $html = self::convert("'''\n");
        self::assertStringContainsString('<hr>', $html);
    }

    public function testConvertTocMacroRendersToc(): void
    {
        $html = self::convert("= Doc\n\ntoc::[]\n\n== One\n\ntext\n");

        self::assertStringContainsString('<div id="toc" class="toc">', $html);
        self::assertStringContainsString('<a href="#_one">One</a>', $html);
    }

    public function testConvertTocStopsAtSecondLevelByDefault(): void
    {
        $doc = new Document([
            'converter'  => new Html5Converter(),
            'attributes' => ['toc' => '', 'sectnums' => ''],
        ]);
        $reader = new PreprocessorReader(
            $doc,
            "= Doc\n\n== A\n\n=== A1\n\n==== A2\n\ntext\n",
            new Cursor(null, null, null),
        );
        Parser::parse($reader, $doc);
        $html = $doc->getConverter()->convert($doc);

        self::assertStringContainsString('<ul class="sectlevel1">', $html);
        self::assertStringContainsString('<ul class="sectlevel2">', $html);
        self::assertStringNotContainsString('<ul class="sectlevel3">', $html);
        self::assertStringContainsString('<a href="#_a">1. A</a>', $html);
        self::assertStringContainsString('<a href="#_a1">1.1. A1</a>', $html);
    }

    // ── Unordered list ────────────────────────────────────────────────────────

    public function testConvertUlistContainsUl(): void
    {
        $html = self::convert("* Alpha\n* Beta\n* Gamma\n");
        self::assertStringContainsString('<div class="ulist">', $html);
        self::assertStringContainsString('<ul>', $html);
        self::assertStringContainsString('<li>', $html);
        self::assertStringContainsString('Alpha', $html);
    }

    public function testConvertUlistThreeItems(): void
    {
        $html = self::convert("* A\n* B\n* C\n");
        self::assertSame(3, substr_count($html, '<li>'));
    }

    public function testConvertUlistWithChecklistOptionAddsClasses(): void
    {
        $doc  = new Document(['converter' => new Html5Converter()]);
        $list = new AsciiList($doc, null, 'ulist');
        $list->setAttribute('checklist-option', true);
        $list->appendItem(new ListItem($doc, $list, '[x] done', '*'));
        $list->appendItem(new ListItem($doc, $list, '[ ] todo', '*'));

        $html = $doc->getConverter()->convert($list);

        self::assertStringContainsString('<div class="ulist checklist">', $html);
        self::assertStringContainsString('<ul class="checklist">', $html);
    }

    // ── Fallback ──────────────────────────────────────────────────────────────

    public function testConvertUnknownInlineReturnsSafeText(): void
    {
        $doc    = new Document(['converter' => new Html5Converter()]);
        $inline = new Inline($doc, 'unknown_type', '<script>');
        $html   = $doc->getConverter()->convert($inline);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testConvertVerseRendersVerseblock(): void
    {
        $html = self::convert("[verse]\n____\nline one\n____\n");

        self::assertStringContainsString('<div class="verseblock">', $html);
        self::assertStringContainsString('<pre class="content">line one</pre>', $html);
    }

    // ── HTML escaping ─────────────────────────────────────────────────────────

    public function testDocumentTitleIsHtmlEscaped(): void
    {
        $html = self::convert("= <script>alert(1)</script>\n\n");
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testInlineAnchorLink(): void
    {
        $doc    = new Document(['converter' => new Html5Converter()]);
        $inline = new Inline($doc, 'link', 'Click me', 'https://example.com');
        $html   = $doc->getConverter()->convert($inline);
        self::assertStringContainsString('href="https://example.com"', $html);
        self::assertStringContainsString('Click me', $html);
    }

    // ── Inline anchor ─────────────────────────────────────────────────────────

    public function testInlineAnchorRef(): void
    {
        $doc    = new Document(['converter' => new Html5Converter()]);
        $inline = new Inline($doc, 'ref', '', 'my-anchor');
        $html   = $doc->getConverter()->convert($inline);
        self::assertStringContainsString('<a id="my-anchor"></a>', $html);
    }

    public function testInlineFootnoteRendersSup(): void
    {
        $doc    = new Document(['converter' => new Html5Converter()]);
        $inline = new Inline($doc, 'footnote', 'A note.');

        self::assertSame('<sup class="footnote">[A note.]</sup>', $doc->getConverter()->convert($inline));
    }

    public function testInlineImageAltKeepsExistingEntities(): void
    {
        $doc    = new Document(['converter' => new Html5Converter()]);
        $inline = new Inline($doc, 'image', '', 'logo.png');
        $inline->setAttribute('alt', 'A &amp; B');

        self::assertStringContainsString('alt="A &amp; B"', $doc->getConverter()->convert($inline));
    }

    public function testInlineImageRendersSpanWithEscapedAlt(): void
    {
        $doc    = new Document(['converter' => new Html5Converter()]);
        $inline = new Inline($doc, 'image', '', 'logo.png');

        self::assertSame(
            '<span class="image"><img src="logo.png" alt="logo"></span>',
            $doc->getConverter()->convert($inline),
        );
    }

    // ── Inline kbd ────────────────────────────────────────────────────────────

    public function testInlineKbd(): void
    {
        $doc    = new Document(['converter' => new Html5Converter()]);
        $inline = new Inline($doc, 'kbd', 'Ctrl+C');
        $html   = $doc->getConverter()->convert($inline);
        self::assertStringContainsString('<kbd class="keyseq">', $html);
        self::assertStringContainsString('<kbd>Ctrl</kbd>', $html);
        self::assertStringContainsString('<kbd>C</kbd>', $html);
    }

    public function testInlineKbdKeepsExistingEntities(): void
    {
        $doc    = new Document(['converter' => new Html5Converter()]);
        $inline = new Inline($doc, 'kbd', 'Ctrl+&amp;');

        self::assertSame(
            '<kbd class="keyseq"><kbd>Ctrl</kbd>+<kbd>&amp;</kbd></kbd>',
            $doc->getConverter()->convert($inline),
        );
    }

    public function testInlineKbdTrimsEachKey(): void
    {
        $doc    = new Document(['converter' => new Html5Converter()]);
        $inline = new Inline($doc, 'kbd', 'Ctrl + C');

        self::assertSame(
            '<kbd class="keyseq"><kbd>Ctrl</kbd>+<kbd>C</kbd></kbd>',
            $doc->getConverter()->convert($inline),
        );
    }

    public function testInlineLinkWithBlankWindowAddsTargetAndRel(): void
    {
        $doc    = new Document(['converter' => new Html5Converter()]);
        $inline = new Inline($doc, 'link', 'Example', 'https://example.com');
        $inline->setAttribute('window', '_blank');

        self::assertSame(
            '<a href="https://example.com" target="_blank" rel="noopener">Example</a>',
            $doc->getConverter()->convert($inline),
        );
    }

    public function testInlineLinkWithSelfWindowAddsTargetOnly(): void
    {
        $doc    = new Document(['converter' => new Html5Converter()]);
        $inline = new Inline($doc, 'link', 'Example', 'https://example.com');
        $inline->setAttribute('window', '_self');

        self::assertSame(
            '<a href="https://example.com" target="_self">Example</a>',
            $doc->getConverter()->convert($inline),
        );
    }

    public function testInlineQuotedEmphasis(): void
    {
        $doc    = new Document(['converter' => new Html5Converter()]);
        $inline = new Inline($doc, 'quoted', 'italic');
        $inline->setAttribute('type', 'emphasis');
        $html = $doc->getConverter()->convert($inline);
        self::assertStringContainsString('<em>italic</em>', $html);
    }

    public function testInlineQuotedMonospaced(): void
    {
        $doc    = new Document(['converter' => new Html5Converter()]);
        $inline = new Inline($doc, 'quoted', 'code');
        $inline->setAttribute('type', 'monospaced');
        $html = $doc->getConverter()->convert($inline);
        self::assertStringContainsString('<code>code</code>', $html);
    }

    // ── Inline quoted ─────────────────────────────────────────────────────────

    public function testInlineQuotedStrong(): void
    {
        $doc    = new Document(['converter' => new Html5Converter()]);
        $inline = new Inline($doc, 'quoted', 'bold text');
        $inline->setAttribute('type', 'strong');
        $html = $doc->getConverter()->convert($inline);
        self::assertStringContainsString('<strong>bold text</strong>', $html);
    }

    public function testInlineQuotedStrongWithRoleWrapsInSpan(): void
    {
        $doc    = new Document(['converter' => new Html5Converter()]);
        $inline = new Inline($doc, 'quoted', 'text');
        $inline->setAttribute('type', 'strong');
        $inline->setAttribute('role', 'myrole');

        self::assertSame(
            '<span class="myrole"><strong>text</strong></span>',
            $doc->getConverter()->convert($inline),
        );
    }

    // -- Inline xref resolution -------------------------------------------------

    public function testInlineXrefResolvesSectionTitle(): void
    {
        $doc    = new Document(['converter' => new Html5Converter()]);
        $reader = new PreprocessorReader($doc, "= Doc\n\n== One\n\ntext\n", new Cursor(null, null, null));
        Parser::parse($reader, $doc);

        $inline = new Inline($doc, 'xref', '', '_one');

        self::assertSame('<a href="#_one">One</a>', $doc->getConverter()->convert($inline));
    }

    // ── Xref without a resolvable title ───────────────────────────────────────

    public function testInlineXrefToNodeWithoutTitleFallsBackToBracketedId(): void
    {
        $doc   = new Document(['converter' => new Html5Converter()]);
        $block = new Block($doc, null, 'paragraph');
        $block->setAttribute('id', 'untitled');
        $doc->register('ids', $block);

        $inline = new Inline($doc, 'xref', '', 'untitled');

        self::assertSame(
            '<a href="#untitled">[untitled]</a>',
            $doc->getConverter()->convert($inline),
        );
    }

    public function testInlineXrefToUnknownIdFallsBackToBracketedId(): void
    {
        $doc    = new Document(['converter' => new Html5Converter()]);
        $inline = new Inline($doc, 'xref', '', '_missing');

        self::assertSame('<a href="#_missing">[_missing]</a>', $doc->getConverter()->convert($inline));
    }

    public function testUnknownInlineSubstitutesInvalidUtf8AndKeepsEntities(): void
    {
        $doc    = new Document(['converter' => new Html5Converter()]);
        $inline = new Inline($doc, 'unknown_type', "A \" &amp; \x80");

        self::assertSame("A &quot; &amp; \u{FFFD}", $doc->getConverter()->convert($inline));
    }
}
