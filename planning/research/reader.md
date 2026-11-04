# Reader Lookahead Research

Status: research note for the 2.0 line. Records current behaviour only; no code
changes are proposed here.

## 1. Scope

How the reader implements lookahead, how much of the parser depends on it, and
which parts of it are structural requirements versus translation artefacts.

Covers `src/Reader/Reader.php`, its only subclass
`src/Reader/PreprocessorReader.php`, and every lookahead call site in
`src/Parser/Parser.php`.

## 2. Summary

Lookahead was implemented, but not as a mark/restore API. `Reader` is a
pull-based reverse stack with a single counter that records whether the line on
top of the stack has already been through the preprocessor.

- `peekLine()` (`Reader.php:99`) is the primitive. `readLine()` (`:160`) is
  `peekLine()` plus a pop.
- Peeking is not observation. On a cache miss it pops a raw line, runs
  `processLine()`, and pushes the processed result back. `processLine()` is
  where include expansion, conditional evaluation, and attribute entries
  happen, so a peek can execute directives.
- `$lookaheadBuffer` is not a buffer of upcoming lines. It is the guard that
  stops the preprocessor running twice on the same line.
- Genuine multi-line lookahead exists once, at a depth of two.

The mechanism is required by the parser design. The fusion of preprocessor into
reader is not.

## 3. Storage model

`Reader::$lines` holds lines in reverse order, so `end()` is the next line and
`array_pop()` is the consume operation. `getLines()` (`:72`) reverses for
display.

`getLines()` and `getSource()` (`:80`) do not process anything. On a reader with
pending includes or conditionals they return the raw stack, not the expanded
document source. Nothing currently depends on this, but it is a trap for any new
caller that assumes `getSource()` is authoritative.

`$mark` (`:23`) is declared and documented as "Reserved for Tier 3 save/restore
(not used in MVP)". No save, restore, or rewind API exists. The Ruby-style
mark/rewind surface was never ported.

## 4. peekLine is execution, not observation

```php
while (true) {
    if ($this->lookaheadBuffer > 0) {
        $line = end($this->lines);
        if (false === $line) {
            return null;
        }
        if ($skipBlank && '' === $line) {
            array_pop($this->lines);
            $this->lookaheadBuffer--;
            continue;
        }
        return $line;
    }

    $line = array_pop($this->lines);
    if (null === $line) {
        if ($this->onEof()) {
            continue;
        }
        return null;
    }

    $processed = $this->processLine($line);
    if (null === $processed) {
        continue;
    }

    $this->lines[] = $processed;
    $this->lookaheadBuffer++;
    ...
}
```

Three branches matter:

1. `$lookaheadBuffer > 0` returns the already processed line sitting on top of
   the stack. No preprocessing runs.
2. Cache miss pops a raw line and runs `processLine()` on it.
3. `processLine()` returning `null` means the line was a directive. The loop
   continues and the directive never reaches the caller.

`processLine()` is overridden in `PreprocessorReader` (`:129`) and dispatches
attribute entries, `include::`, `ifdef`/`ifndef`/`else`/`endif`, and front
matter. `onEof()` (`:113`) restores the parent include context when an included
file is exhausted.

Consequences:

- A peek can push an include context.
- A peek can set an attribute.
- A peek can skip an entire conditional branch.
- `hasMoreLines()` (`:84`) is `peekLine() !== null`, and `isNextLineEmpty()`
  (`:90`) is `peekLine() === ''`. Every loop guard in the parser therefore
  executes the preprocessor.

## 5. $lookaheadBuffer semantics

`$lookaheadBuffer` counts lines at the top of the stack that have already been
processed. It is not a count of future lines and it is not a count of remaining
lines.

The invariant is that the top `$lookaheadBuffer` entries of `$lines` are
processed and every entry below them is raw. Both `unshiftLine()` (`:242`) and
`unshiftLines()` (`:252`) reset the counter to `0`, which asserts that
*everything* on the stack is raw.

That assertion is false for `peekLines()` and for
`readLinesUntil(['preserve_last_line' => true])`, because both push back lines
that already came out of `readLine()` and are therefore already processed. The
reset is only harmless because of the invariant in section 8.

## 6. Call site inventory

All lookahead in `Parser.php`, 15 sites:

| Line | Call | Purpose |
|---|---|---|
| 96 | `peekLine()` | `nextBlock` initial classification |
| 104 | `peekLine()` | re-peek after `skipBlankLines()` |
| 111 | `peekLine()` | re-peek after `parseBlockMetadataLines()` |
| 399 | `peekLine()` | metadata loop: `[[id]]`, `[attrlist]`, `.Title` |
| 471 | `peekLine()` | description text position in a definition list term |
| 496 | `peekLine()` | document header, level-0 title probe |
| 512 | `peekLine()` | document header, author line probe |
| 524 | `peekLine()` | document header, revision line probe |
| 558 | `peekLine()` | list item continuation loop |
| 567 | `peekLine()` | re-peek after consuming a continuation marker |
| 606 | `peekLine()` | wrapped-line detection |
| 629 | `peekLines(2)` | list item `+` continuation probe |
| 1022 | `peekLine()` | block body loop |
| 1132 | `peekLine()` | document body loop |
| 1168 | `peekLine()` | section body loop |

`peekLine()` is also called four times inside `Reader.php` itself:
`hasMoreLines()`, `isNextLineEmpty()`, `readLine()`, and `skipCommentLines()`.

Every block classification decision in the parser is made on a peek, followed by
consumption in the dispatch target. There is no token buffer and no other way to
observe the next line.

The document header is a three-deep sequential probe (`:496`, `:512`, `:524`)
built out of single peeks, consuming only when a pattern matches.

## 7. The only multi-line lookahead

`Parser.php:629` calls `$reader->peekLines(2)` to decide whether a list item is
followed by a `+` continuation block.

`peekLines()` (`:149`) is implemented as consume then restore:

```php
$lines = $this->readLines($count);
$this->unshiftLines($lines);
```

`readLines()` advances the cursor once per line through `readLine()`. `peekLines()`
does not rewind the cursor. It rewinds the line stack only.

## 8. The idempotency invariant

`unshiftLines()` resets `$lookaheadBuffer = 0` while pushing back lines that
have already been processed. The next `peekLine()` therefore pops the top
processed line and runs `processLine()` on it a second time.

This is only safe because `processLine()` is idempotent for every line it returns
non-null for. A line that came back non-null was not a directive, so
re-processing returns it unchanged. Directives never reach the pushed-back array,
because `processLine()` returning `null` makes `peekLine()` continue to the next
line.

That invariant is undocumented and unenforced. If `processLine()` ever gains an
effect on a content line, `peekLines(2)` double-applies it silently at the single
list continuation site.

## 9. Cursor drift

`readLine()` advances the cursor (`:171`). `peekLine()` never does. Directives
consumed inside a peek therefore do not advance the line counter, so reported
line numbers drift in any document that uses `include::`, `ifdef::`, or
attribute entries. Peeking does not rewind the cursor either, so a `peekLines()`
call leaves the counter advanced past lines that were not consumed.

This is an approximation, not a correctness bug for rendering, but it bounds the
quality of any line-numbered diagnostics built on top of `Cursor`.

## 10. Required versus incidental

Required by the design:

- A peek primitive. The parser is a single-line classifier, and Asciidoctor is
too, so the parser must be able to see the next line without consuming it.
- Two-line lookahead at exactly one site, for the list `+` continuation check.

Incidental to the design:

- The `$lookaheadBuffer` counter. It exists only because the preprocessor is
  lazy and fused into the reader.
- The idempotency requirement on `processLine()`.
- Cursor drift around directives.

## 11. Implications for 2.0

If the preprocessor becomes its own expansion pass, producing a flat line list
with an origin map, then:

- Peek becomes pure observation. No counter, no idempotency requirement.
- Cursor drift disappears, because line numbers come from the origin map rather
  than from a counter advanced at read time.
- The reader stops being a place where directives execute, which makes it
  testable as a plain sequence source.

This is a parse-side concern and does not affect the laminas-view renderer work
directly. It does affect the bus pipeline question: a preprocessor fused into
the reader cannot be a pipeline stage, so any event boundary between "source"
and "AST" requires the expansion pass to be separated first.
