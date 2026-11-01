<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\Cli;

use RuntimeException;

/**
 * Thrown when `Options::parse()` encounters an unrecognised or invalid
 * command-line argument.
 */
final class ParseException extends RuntimeException {}
