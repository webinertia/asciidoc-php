# asciidoc-php: Copilot Agent Instructions

## Read this before acting: fleet working agreements

`vendor/webware/webware-tools/agent-working-agreements.md` is authoritative for how work is done
in this fleet, and it overrides anything else in this file or in any local note. Read it before
acting, and again before reporting. The non-negotiables:

- Do exactly the scope asked - only the files, branches and repositories named. No adjacent edits,
  no cleanup, no onboarding, no surveys.
- Anything else you notice gets one line at most, with no proposed fix.
- Never close a reply with a condensed restatement of it, and never present an inference as a
  measurement.
- Every change lands through a pull request. Releases and tags belong to the repository owner.

## Mago Analyzer Docblock Types

When resolving `mago analyze` findings (e.g. `mixed-argument`, `mixed-assignment`,
`missing-return-type`), consult
[`vendor/webware/webware-tools/mago-analysis-types.md`](../vendor/webware/webware-tools/mago-analysis-types.md)
for the full list of docblock-only types Mago's analyzer supports (`positive-int`,
`non-empty-string`, `list<T>`, `key-of<T>`, etc.) before falling back to `mixed`. webware-tools is
always a dependency, so this file does not need to be duplicated per package.

### Class-reference string types

Mago distinguishes what a `*::class` string may reference:

- `class-string<T>` - a concrete **class**
- `interface-string<T>` - an **interface**
- `enum-string<T>` - an **enum**
- `trait-string<T>` - a **trait**
- `class-like-string<T>` - any of class / interface / enum

Webware aliases against interfaces and builds to interfaces, so prefer the precise form:

- DI aliases: `array<interface-string, class-string>` (e.g. `UserRepositoryInterface::class => UserRepository::class`)
- handler/command/query maps: `array<class-string, class-string>` (e.g. `FetchUsers::class => FetchUsersHandler::class`)

Do not flatten an interface-keyed map to `array<class-string, class-string>`.

## PHPUnit Mock vs Stub Rules

PHPUnit 13 enforces a strict separation between mocks and stubs. Violating these rules produces `PHPUnit Notices` that cause test suite failures under `failOnNotice="true"` (configured in `phpunit.xml.dist`).

### Rules

- **Use `createStub()`** when the test double only needs to return values (`method()->willReturn()`, `method()->willReturnCallback()`). No expectations are configured.
- **Use `createMock()`** only when the test verifies behavior with `expects()` (e.g. `expects($this->once())`, `expects($this->never())`).
- **Never** call `createMock()` without also calling `expects()` on at least one method - PHPUnit 13 will issue a notice.
- **Remove** `/** @var ClassName&MockObject */` annotations and `MockObject` intersection types on variables created with `createStub()`.
- **Remove** the `use PHPUnit\Framework\MockObject\MockObject;` import from any file where no `createMock()` + `expects()` usage remains.

### Examples

```php
// CORRECT - stub returns value, no expectations
$container = $this->createStub(ContainerInterface::class);
$container->method('get')->willReturnCallback(static fn(string $id): mixed => ...);

// CORRECT - mock verifies behavior with expects()
$handler = $this->createMock(ErrorHandler::class);
$handler->expects($this->once())->method('attachListener');

// WRONG - createMock() with no expects() triggers PHPUnit notice
$logger = $this->createMock(Logger::class);
$logger->method('withName')->willReturn($logger); // should be createStub()
```

## PHPUnit Coverage Metadata Rules

`phpunit.xml.dist` has `requireCoverageMetadata="true"`. Every test class **must** have:

1. `#[CoversClass(ClassName::class)]` - one per source class under test.
2. `#[CoversMethod(ClassName::class, 'methodName')]` - one per public/protected method exercised.
3. `use PHPUnit\Framework\Attributes\CoversClass;` and `use PHPUnit\Framework\Attributes\CoversMethod;` imports.

## Package-specific guidelines

### What this package is

`webware/asciidoc-php` is a PHP 8.4+ CLI application that converts AsciiDoc
(`.adoc`) source files to HTML5. The reference implementation is Asciidoctor
2.0.26 (Ruby). All HTML output must match native Asciidoctor's output
structurally and visually. See `planning/README.md` for the design document
index.

### Build and test

Every gate runs inside the container, never on host PHP. Start it once, then
`exec` into it:

```bash
docker compose up -d
docker compose exec tooling composer install
docker compose exec tooling composer check-all
docker compose down
```

Individual gates:

```bash
composer check-all          # sa (lint + analyze + guard), unit tests, integration tests
composer sa                 # mago lint && mago analyze && mago guard
composer test               # unit tests only
composer test-integration   # integration tests only
composer test-coverage      # clover.xml plus a text summary
composer mutation-test      # infection
mago format --check         # formatting gate, run directly
```

Compare output against native Asciidoctor:

```bash
php bin/asciidoc-php docs/usage.adoc -o test/php-output/usage.html
asciidoctor docs/usage.adoc -o test/reference/usage.html
diff test/php-output/usage.html test/reference/usage.html
```

Benchmark (sequential vs concurrent):

```bash
composer benchmark          # per-file CPU timings
composer benchmark-json     # JSON output
```

### Architecture

```text
bin/asciidoc-php -> Cli\Application -> Cli\Invoker
                                          |
                         +----------------+-----------------+
                         |  single file: direct             |
                         |  multi-file: Async\TaskGroup     |
                         |  (falls back to sequential if    |
                         |   true-async ext not loaded)     |
                         +----------------------------------+
                                          |
                              Asciidoc::convertFile()
                                          |
                         PreprocessorReader -> Parser -> Document (AST)
                                                           |
                                                    Html5Converter
```

Key constraints:

- `Parser`: all methods `public static`; zero instance state
- `SubstitutorsTrait`: mixed into `AbstractNode`; pure string functions
- `Html5Converter`: stateless per call; pure string building
- Core components contain zero TrueAsync primitives; they work identically in
  sync and async contexts
- Concurrency lives only in `Cli\Invoker`

### Important conventions

#### Substitution pipeline order (SubstitutorsTrait)

`specialcharacters -> quotes -> attributes -> replacements -> macros -> post_replacements`

`specialcharacters` runs before `macros`. By the time `subMacros()` runs, `<<`
is already `&lt;&lt;` and `>>` is `&gt;&gt;`. Cross-reference patterns must match
the entity-encoded form.

`subSpecialChars()` uses `ENT_NOQUOTES`: only `&`, `<`, `>` are encoded;
double quotes are not encoded.

#### Verbatim block preprocessing

`PreprocessorReader::setVerbatimMode(true)` bypasses all preprocessing
(attribute entries, `//` comments, `include::` directives) inside `----`
delimited blocks. Call it in `Parser::parseDelimitedBlock()` before reading
verbatim or raw block content.

#### TrueAsync guard pattern

```php
if (count($files) > 1 && class_exists(\Async\TaskGroup::class)) {
    // concurrent path
} else {
    // sequential fallback
}
```

Never pass `concurrency: null` to `TaskGroup()`: omit the argument for
unbounded, or pass a positive `int` only.

#### CSS and assets

The Asciidoctor default stylesheet is embedded inline from
`resources/css/asciidoctor.css`. Highlight.js is pinned to v9.18.3 CDN using
the `highlightBlock()` init API (not `highlightAll()`). Google Fonts are loaded
via `Html5Converter::renderWebFonts()`.

`resources/css/asciidoctor.css` is redistributed unmodified under Asciidoctor's
MIT license, and `THIRD-PARTY-NOTICES.md` depends on that fact. Never edit it.

#### Known intentional diffs vs native Asciidoctor

These five differences are expected and must not be fixed:

1. `<meta http-equiv="X-UA-Compatible">`: we do not emit this
2. `<meta name="generator">`: different tool name
3. Google Fonts `<link>` position (one line earlier in ours)
4. `<col style="width: 50%">` vs `50.0002%`: rounding
5. Trailing newline at EOF

### PHP version and async runtime

- `composer.json` targets `~8.4.1 || ~8.5.0`; `config.platform.php` is `8.4.99`
- The dev image PHP version is the `PHP_VERSION` build argument in `compose.yml`
- The full async activation checklist (for when PHP 8.6 releases stable) is in
  `planning/10-async-runtime.md`, section "Activating Full Async Support"
- `composer check-all` is used instead of `composer check` to avoid a Composer
  2.10 built-in command collision