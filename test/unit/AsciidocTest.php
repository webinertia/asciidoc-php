<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Webware\AsciidocPhp\Asciidoc;

#[CoversClass(Asciidoc::class)]
final class AsciidocTest extends TestCase
{
    private string $dir;

    public function testConvertFileDefaultsBaseDirToFileDirectory(): void
    {
        file_put_contents("{$this->dir}/child.adoc", "Included body.\n");
        $parent = "{$this->dir}/parent.adoc";
        file_put_contents($parent, "= Parent\n\ninclude::child.adoc[]\n");

        $html = Asciidoc::convertFile($parent);

        self::assertStringContainsString('Included body.', $html);
    }

    public function testConvertFileHonoursExplicitBaseDir(): void
    {
        mkdir("{$this->dir}/other");
        file_put_contents("{$this->dir}/other/child.adoc", "Other body.\n");
        $parent = "{$this->dir}/parent.adoc";
        file_put_contents($parent, "= Parent\n\ninclude::child.adoc[]\n");

        $html = Asciidoc::convertFile($parent, ['base_dir' => "{$this->dir}/other"]);

        self::assertStringContainsString('Other body.', $html);

        unlink("{$this->dir}/other/child.adoc");
        rmdir("{$this->dir}/other");
    }

    public function testConvertFileThrowsWhenFileMissing(): void
    {
        $this->expectException(RuntimeException::class);

        Asciidoc::convertFile("{$this->dir}/missing.adoc");
    }

    public function testConvertWithHeaderFooterIsAFullDocument(): void
    {
        $html = Asciidoc::convert("= Title\n\nBody.\n");

        self::assertStringContainsString('<html', $html);
        self::assertStringContainsString('<title>Title</title>', $html);
    }

    public function testConvertWithoutHeaderFooterIsEmbedded(): void
    {
        $html = Asciidoc::convert("= Title\n\nBody.\n", ['header_footer' => false]);

        self::assertStringNotContainsString('<html', $html);
        self::assertStringContainsString('Body.', $html);
    }

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/asciidoc-php-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob("{$this->dir}/*") ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }
}
