<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\Substitutor;

/**
 * Renders the `link:` inline macro and turns bare URLs in prose into anchors.
 *
 * Pure string functions with no instance state, so the same output is produced
 * in sync and async contexts. Kept out of SubstitutorsTrait so the trait stays
 * below the complexity threshold.
 *
 * @internal
 */
final class LinkMacros
{
    /**
     * Regions the bare-URL autolinker must copy through untouched, matched whole.
     *
     * Anchors already produced in this same pass, inline code spans, inline
     * passthroughs, and any other tag this converter emitted (so a URL sitting in
     * an attribute value is never rewritten).
     */
    private const string AUTOLINK_EXCLUDED = '/(<a\b[^>]*>.*?<\/a>|<code\b[^>]*>.*?<\/code>|pass:\[[^\]]*\]|\+{3}.*?\+{3}|<[a-z][^>]*>)/s';

    /**
     * Apply the inline link macros to a run of text.
     *
     * The `link:` macro runs first so its output is never scanned a second time
     * by the autolinker.
     */
    public static function apply(string $text): string
    {
        $linked = (string) preg_replace_callback(Rx::LINK_MACRO, self::renderMatch(...), $text);

        return self::autoLink($linked);
    }

    /**
     * Turn bare URLs in prose into anchors.
     *
     * Verbatim blocks never reach this pass: listing, literal and pass blocks are
     * VERBATIM or RAW, so they get VERBATIM_SUBS or no subs at all. Anchors from an
     * earlier macro, inline code spans and inline passthroughs are split out and
     * copied through unchanged.
     */
    private static function autoLink(string $text): string
    {
        $parts = preg_split(
            self::AUTOLINK_EXCLUDED,
            $text,
            limit: -1,
            flags: PREG_SPLIT_DELIM_CAPTURE,
        );

        if (false === $parts) {
            return $text;
        }

        $result = '';
        foreach ($parts as $index => $part) {
            if (1 === ($index % 2)) {
                $result .= $part;
                continue;
            }
            $result .= self::autoLinkSegment($part);
        }

        return $result;
    }

    /**
     * @param array<array-key, string> $m
     */
    private static function autoLinkMatch(array $m): string
    {
        $url  = $m[0] ?? '';
        $href = self::escapeHref($url);

        return "<a href=\"{$href}\">{$url}</a>";
    }

    private static function autoLinkSegment(string $text): string
    {
        return (string) preg_replace_callback(Rx::URL_MACRO, self::autoLinkMatch(...), $text);
    }

    /**
     * Escape a target for use inside a double-quoted HTML attribute.
     *
     * `double_encode` is false because specialcharacters has already turned every
     * `&` in the surrounding text into `&amp;`; escaping again would yield
     * `&amp;amp;`.
     */
    private static function escapeHref(string $target): string
    {
        return htmlspecialchars(
            $target,
            flags: ENT_COMPAT | ENT_SUBSTITUTE,
            encoding: 'UTF-8',
            double_encode: false,
        );
    }

    /**
     * Split a link attribute list into its label and its `window` value.
     *
     * Only `window` is honoured. Any other named attribute is dropped rather
     * than emitted into the output.
     *
     * @return array{0: string, 1: string}
     */
    private static function parseAttributes(string $attributes): array
    {
        if ('' === trim($attributes)) {
            return ['', ''];
        }

        $parts  = explode(',', $attributes);
        $label  = trim(array_shift($parts));
        $window = '';

        foreach ($parts as $part) {
            $pair = explode('=', $part, limit: 2);
            if (2 !== count($pair) || 'window' !== trim($pair[0])) {
                continue;
            }
            $window = trim($pair[1] ?? '', characters: " \t\"'");
        }

        return [$label, $window];
    }

    /**
     * Render a `link:` macro.
     *
     * The target is emitted exactly as authored. Extension substitution, `.adoc`
     * to `.html` rewriting and base-path prefixing belong to the consumer, which
     * owns that policy through its link rewriter.
     */
    private static function renderMacro(string $target, string $attributes): string
    {
        [$label, $window] = self::parseAttributes($attributes);

        if ('' === $label) {
            $label = $target;
        }

        $href       = self::escapeHref($target);
        $targetAttr = '' !== $window ? " target=\"{$window}\"" : '';
        $relAttr    = '_blank' === $window ? ' rel="noopener"' : '';

        return "<a href=\"{$href}\"{$targetAttr}{$relAttr}>{$label}</a>";
    }

    /**
     * @param array<array-key, string> $m
     */
    private static function renderMatch(array $m): string
    {
        return self::renderMacro($m[1] ?? '', $m[2] ?? '');
    }
}
