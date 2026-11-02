<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\Asciidoc;

use function file_get_contents;
use function preg_replace;

/**
 * Byte for byte render gate for the frozen usage reference fixture.
 *
 * The pair in test/asset/usage/ is deliberately frozen: docs/v1/usage.adoc is expected to diverge
 * from it as the documentation changes, and that divergence must not fail this test. Never repoint
 * the fixture at the docs source.
 */
#[CoversClass(Asciidoc::class)]
#[CoversMethod(Asciidoc::class, 'convertFile')]
final class UsageRenderFixtureTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/../asset/usage';

    #[Test]
    public function fixtureSourceRendersToExpectedHtml(): void
    {
        $expected = file_get_contents(self::FIXTURE_DIR . '/expected.html');

        if (false === $expected) {
            $this->fail('Unable to read the frozen fixture at test/asset/usage/expected.html');
        }

        $actual = Asciidoc::convertFile(self::FIXTURE_DIR . '/usage.adoc');

        $this->assertSame(
            $this->normaliseTimestamp($expected),
            $this->normaliseTimestamp($actual),
        );
    }

    /**
     * Replaces only the generated "Last updated" line, which Html5Converter::renderDocumentFooter()
     * stamps from the current clock, so two renders of the same source compare equal.
     */
    private function normaliseTimestamp(string $html): string
    {
        $normalised = preg_replace(
            pattern    : '/^Last updated \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} UTC$/m',
            replacement: 'Last updated <timestamp>',
            subject    : $html,
        );

        return $normalised ?? $html;
    }
}
