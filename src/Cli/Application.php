<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\Cli;

use Closure;
use Throwable;

/**
 * Entry-point for the `asciidoc-php` binary.
 *
 * Parses arguments, resolves help/version short-circuits, then delegates to
 * Invoker.  All uncaught errors are caught here and written to STDERR.
 */
final class Application
{
    private const VERSION = '0.1.0';

    private const HELP = <<<'HELP'
        Usage: asciidoc-php [options] FILE [FILE ...]
               asciidoc-php [options] -          (read from STDIN)

        Options:
          -b, --backend BACKEND
                Output backend (default: html5)

          -d, --doctype DOCTYPE
                Document type: article | book | manpage | inline (default: article)

          -o, --out-file FILE
                Write output to FILE. Use '-' for STDOUT.

          -D, --destination-dir DIR
                Write all output files into DIR.

          -B, --base-dir DIR
                Set the base directory for include:: resolution.

          -S, --safe-mode MODE
                Set safe mode: unsafe | safe | server | secure (default: safe)

          -a, --attribute KEY[=VALUE]
                Set a document attribute. Repeatable.

          -s, --no-header-footer
                Suppress HTML header and footer (embedded/fragment output).

          --concurrency N
                Maximum number of files to convert in parallel (default: 0 = unbounded).

          -q, --quiet        Suppress all messages except errors.
          -v, --verbose      Log each converted file.
          -t, --timings      Print timing breakdown.
              --trace        Print stack trace on error.
          -h, --help         Print this help message and exit.
              --version      Print the library version and exit.

        Examples:
          asciidoc-php docs/index.adoc
          asciidoc-php -D html/ docs/*.adoc
          asciidoc-php -b html5 -a toc docs/guide.adoc
          asciidoc-php -s -o - docs/fragment.adoc | cat
        HELP;

    /**
     * Diagnostics sink.  Defaults to a writer for the process STDERR stream;
     * callers that must not touch the process streams (tests, embedders) pass
     * their own.
     *
     * @var Closure(string): void
     */
    private readonly Closure $stderr;

    /**
     * @param list<string>                 $args   Raw $argv.
     * @param (Closure(string): void)|null $stderr Diagnostics sink, defaults to STDERR.
     */
    public function __construct(
        private readonly array $args,
        ?Closure $stderr = null,
    ) {
        $this->stderr = $stderr ?? self::stderrWriter();
    }

    /**
     * @return Closure(string): void
     */
    private static function stderrWriter(): Closure
    {
        return static function (string $message): void {
            fwrite(STDERR, $message);
        };
    }

    /**
     * Parse arguments, execute conversion, and return a POSIX exit code.
     */
    public function run(): int
    {
        try {
            $options = Options::parse($this->args);
        } catch (ParseException $e) {
            ($this->stderr)("asciidoc-php: {$e->getMessage()}\n");
            return 1;
        }

        if ($options->help) {
            echo self::HELP . "\n";
            return 0;
        }

        if ($options->version) {
            echo 'asciidoc-php ' . self::VERSION . "\n";
            return 0;
        }

        if ([] === $options->inputFiles) {
            ($this->stderr)("asciidoc-php: No input files specified. Use -h for help.\n");
            return 1;
        }

        try {
            $invoker = new Invoker($options, null, $this->stderr);
            return $invoker->invoke();
        } catch (Throwable $e) {
            if ($options->trace) {
                ($this->stderr)((string) $e . "\n");
            } else {
                ($this->stderr)("asciidoc-php: {$e->getMessage()}\n");
            }
            return 1;
        }
    }
}
