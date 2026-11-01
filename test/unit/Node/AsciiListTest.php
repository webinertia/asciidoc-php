<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\Node;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\Document;
use Webware\AsciidocPhp\Node\AsciiList;
use Webware\AsciidocPhp\Node\ListItem;

#[CoversClass(AsciiList::class)]
final class AsciiListTest extends TestCase
{
    private Document $doc;

    public function testAppendItemAlsoPopulatesBlocks(): void
    {
        $list = new AsciiList($this->doc, null, 'ulist');
        $item = new ListItem($this->doc, $list, 'One', '*');
        $list->appendItem($item);
        self::assertSame([$item], $list->getBlocks());
    }

    // ── appendItem / getItems ─────────────────────────────────────────────────

    public function testAppendItemAndGetItems(): void
    {
        $list  = new AsciiList($this->doc, null, 'ulist');
        $item1 = new ListItem($this->doc, $list, 'First', '*');
        $item2 = new ListItem($this->doc, $list, 'Second', '*');
        $list->appendItem($item1);
        $list->appendItem($item2);

        self::assertSame([$item1, $item2], $list->getItems());
    }

    public function testContextAndNodeName(): void
    {
        $list = new AsciiList($this->doc, null, 'ulist');
        self::assertSame('ulist', $list->getContext());
        self::assertSame('ulist', $list->getNodeName());
    }

    public function testHasItemsFalseWhenEmpty(): void
    {
        $list = new AsciiList($this->doc, null, 'ulist');
        self::assertFalse($list->hasItems());
    }

    public function testHasItemsTrueAfterAppend(): void
    {
        $list = new AsciiList($this->doc, null, 'ulist');
        $list->appendItem(new ListItem($this->doc, $list, 'X', '*'));
        self::assertTrue($list->hasItems());
    }

    public function testIsOutlineFalseForColist(): void
    {
        $list = new AsciiList($this->doc, null, 'colist');
        self::assertFalse($list->isOutline());
    }

    public function testIsOutlineFalseForDlist(): void
    {
        $list = new AsciiList($this->doc, null, 'dlist');
        self::assertFalse($list->isOutline());
    }

    public function testIsOutlineTrueForOlist(): void
    {
        $list = new AsciiList($this->doc, null, 'olist');
        self::assertTrue($list->isOutline());
    }

    // ── isOutline ─────────────────────────────────────────────────────────────

    public function testIsOutlineTrueForUlist(): void
    {
        $list = new AsciiList($this->doc, null, 'ulist');
        self::assertTrue($list->isOutline());
    }

    protected function setUp(): void
    {
        $this->doc = new Document();
    }
}
