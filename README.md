# asciidoc-php

[![PHP Version](https://img.shields.io/packagist/php-v/webware/asciidoc-php)](https://packagist.org/packages/webware/asciidoc-php)
[![Latest Version](https://img.shields.io/packagist/v/webware/asciidoc-php)](https://packagist.org/packages/webware/asciidoc-php)
[![License](https://img.shields.io/github/license/webinertia/asciidoc-php)](LICENSE)
[![Required CI](https://github.com/webinertia/asciidoc-php/actions/workflows/required/webinertia/.github/.github/workflows/org-required-ci.yml/badge.svg)](https://github.com/webinertia/asciidoc-php/actions/workflows/required/webinertia/.github/.github/workflows/org-required-ci.yml)
[![codecov](https://codecov.io/gh/webinertia/asciidoc-php/graph/badge.svg)](https://codecov.io/gh/webinertia/asciidoc-php)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fwebinertia%2Fasciidoc-php%2F0.1.x)](https://dashboard.stryker-mutator.io/reports/github.com/webinertia/asciidoc-php/0.1.x)

`webware/asciidoc-php` is a PHP port of the Asciidoctor toolchain that converts
AsciiDoc source files to HTML5. The package namespace is
`Webware\AsciidocPhp`.

This project is a port of the AsciiDoc toolchain into PHP. It follows
[Asciidoctor](https://asciidoctor.org/) as its reference implementation and
targets documentation-quality feature coverage rather than 1:1 API
compatibility with Asciidoctor. It is not affiliated with, sponsored by, or
endorsed by the Asciidoctor project.

## Requirements

- PHP 8.4 or PHP 8.5, with the CLI SAPI available as `php`
- Composer, to install the package and its autoloader
- No PHP runtime dependencies beyond PHP itself

Install with Composer:

```bash
composer require webware/asciidoc-php
```

The command line entry point is installed as `vendor/bin/asciidoc-php`. When
working from a git clone, run `php bin/asciidoc-php` instead.

## Usage

Convert a single file, writing the HTML next to the source:

```bash
vendor/bin/asciidoc-php docs/guide.adoc
```

Convert to a specific output file:

```bash
vendor/bin/asciidoc-php docs/guide.adoc -o build/guide.html
```

Convert several files into one output directory:

```bash
vendor/bin/asciidoc-php -D build/html docs/*.adoc
```

Read from STDIN and write an embedded fragment to STDOUT:

```bash
cat fragment.adoc | vendor/bin/asciidoc-php -s -o - -
```

Set document attributes, which are applied before the header is read:

```bash
vendor/bin/asciidoc-php -a toc=left -a sectnums -a icons=font docs/guide.adoc
```

### Command line options

| Option | Description |
| --- | --- |
| `-b`, `--backend BACKEND` | Output backend. Only `html5` is implemented; the default is `html5`. |
| `-d`, `--doctype DOCTYPE` | Document type: `article` (default), `book`, `manpage`, or `inline`. |
| `-o`, `--out-file FILE` | Write output to `FILE`. Use `-` for STDOUT. |
| `-D`, `--destination-dir DIR` | Write all converted files into `DIR`, creating it if needed. |
| `-B`, `--base-dir DIR` | Base directory used to resolve `include::` directives. |
| `-S`, `--safe-mode MODE` | Trust level: `unsafe`, `safe` (default), `server`, or `secure`. |
| `-a`, `--attribute KEY[=VALUE]` | Set a document attribute. Repeatable. Use `KEY!` to unset. |
| `-s`, `--no-header-footer` | Emit an embedded fragment without the HTML document wrapper. |
| `--concurrency N` | Cap concurrent conversions when multiple files are given. `0` means sequential. |
| `-q`, `--quiet` | Suppress informational output; fatal errors still go to STDERR. |
| `-v`, `--verbose` | Log each converted file as it completes. |
| `-t`, `--timings` | Print per-file timing information. |
| `--trace` | Print a full PHP stack trace when a conversion error is caught. |
| `-h`, `--help` | Print help and exit. |
| `--version` | Print the library version and exit. |
| `--` | Treat all remaining arguments as input files. |

A single `-` as the input file reads from STDIN.

The same conversion is available from PHP through the facade:

```php
use Webware\AsciidocPhp\Asciidoc;

$html = Asciidoc::convert('= Hello World');
$html = Asciidoc::convertFile('/path/to/guide.adoc', [
    'attributes' => ['toc' => 'left', 'sectnums' => ''],
]);
```

See the [reference page](https://docs.webinertia.net/asciidoc-php/v1/usage.html)
for the full option reference, the PHP API option keys, YAML front matter
handling, and the built-in document attributes.

## Project status

The project is at version 0.1 and is under active development. Its delivery
target is Tier 1 and Tier 2 of the MVP scope in
[planning/00-mvp-scope.md](planning/00-mvp-scope.md).

Implemented for the HTML5 backend:

- Document header: title, author line, revision line, and attribute entries
- Sections, paragraphs, preamble, block titles, block IDs, and roles
- Inline formatting: bold, italic, monospace, mark, superscript, subscript,
  passthrough, footnotes, and hard line breaks
- Lists: unordered, ordered, description, and callout lists
- Delimited blocks: listing, literal, example, sidebar, quote, verse, open,
  and passthrough
- Admonition paragraphs and blocks
- Tables in PSV format, including basic colspan and rowspan
- Links, anchors, cross-references, and inline and block images
- Table of contents, section numbering, and syntax highlighting through
  highlight.js on the client
- Preprocessing: `include::`, `ifdef::`, `ifndef::`, `endif::`, comments,
  attribute entries, and optional YAML front matter
- Safe modes, document attributes, and batch conversion of multiple files

Not yet implemented or only partially implemented:

- The DocBook and manpage output converters
- Non-PSV table formats and per-cell style overrides
- Server-side syntax highlighting
- Full `ifeval::` evaluation and the docinfo processor
- The extension system and template-based converters
- A packaged GitHub Action

Only the `html5` backend is implemented. The `book`, `manpage`, and `inline`
doctypes are accepted, but `article` is the most exercised and best supported.
Tier 3 features are partial and are not yet covered by a compatibility
guarantee.

## Credits and attribution

### AsciiDoc

AsciiDoc, the markup language, was created by Stuart Rackham. The original
implementation, AsciiDoc.py, is licensed under GPL-2.0. This project does not
include or derive from AsciiDoc.py: its parser, reader, and converter are
independent PHP implementations written against the AsciiDoc language and the
Asciidoctor behaviour described below.

### Asciidoctor

Asciidoctor, the Ruby implementation, is the reference implementation this port
follows. The specific reference targeted by this port is the fork at
<https://github.com/tyrsson/asciidoctor>, which is a fork of
<https://github.com/asciidoctor/asciidoctor>. Upstream Asciidoctor is licensed
under the MIT License and is copyright (C) 2012-present Dan Allen, Sarah White,
Ryan Waldron, and the individual contributors to Asciidoctor. The fork is
licensed under the same MIT License and carries the same upstream copyright
line unchanged. See <https://asciidoctor.org>.

### Bundled stylesheet

The default stylesheet in `resources/css/asciidoctor.css` is Asciidoctor's
MIT-licensed default stylesheet, redistributed unmodified. The default
converter embeds it inline in generated HTML unless `linkcss` or `noembed` is
set.

### Notices

Full third-party license text and attribution are in
[THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md).

## License

This project is licensed under the MIT License. See [LICENSE](LICENSE) for the
full text. The MIT License permits use, copying, modification, and
redistribution, including in commercial and closed-source software, provided
the copyright notice and permission notice are retained.
