<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * Public-rendering status oracle for the docs pages backing exported
 * surfaces (components.json / api-map.json / blocks.json).
 *
 * THE shared visibility check for every export command — one parser,
 * one contract, no per-command frontmatter drift. The docs site renders
 * a page publicly ONLY when its frontmatter does not restrict it (a
 * missing field is the downstream parser's default — public) AND it is
 * not `draft: true`. Everything else exists on disk but is not
 * publicly rendered.
 *
 * Three statuses, deliberately distinct:
 *
 *   - PUBLIC:  page exists and renders publicly → full advertising
 *     surface (docs_url emitted, entry kept everywhere).
 *   - STAGED:  page exists but is NOT publicly rendered → public
 *     manifests must drop the entry ENTIRELY (not merely
 *     docs_url=null).
 *   - MISSING: no dedicated page on disk. NOT the same as a STAGED
 *     page —
 *     this is the sub-component pattern (toast-region, glass,
 *     reading-*, kanban-column) documented on a parent page. The
 *     entry stays in every manifest; only its docs_url is null.
 */
final class DocsVisibility
{
    /** Page exists and renders publicly. */
    public const STATUS_PUBLIC = 'public';

    /** Page exists but is not publicly rendered. */
    public const STATUS_STAGED = 'staged';

    /** No dedicated page on disk (documented on a parent page). */
    public const STATUS_MISSING = 'missing';

    /**
     * Status of a component's dedicated docs page
     * (docs/components/{name}.md).
     */
    public static function componentPageStatus(string $name): string
    {
        return self::pageStatus(dirname(__DIR__, 2)."/docs/components/{$name}.md");
    }

    /**
     * Status of an arbitrary docs page by absolute path.
     */
    public static function pageStatus(string $path): string
    {
        if (! file_exists($path)) {
            return self::STATUS_MISSING;
        }

        $content = (string) file_get_contents($path);

        // No frontmatter → the downstream Markdown parser defaults to
        // guest + non-draft → publicly rendered.
        if (! str_starts_with($content, '---')) {
            return self::STATUS_PUBLIC;
        }

        $closing = strpos($content, "\n---", 3);
        if ($closing === false) {
            return self::STATUS_PUBLIC;
        }

        $frontmatter = substr($content, 3, $closing - 3);

        // `visibility:` value on its own line. Strict shape avoids
        // false-positives on prose mentions of the literal field name.
        // Any value other than guest hides the page from the public.
        if (preg_match('/^\s*visibility\s*:\s*([a-z]+)\s*$/mi', $frontmatter, $m) === 1
            && strtolower($m[1]) !== 'guest') {
            return self::STATUS_STAGED;
        }

        // `draft: true` pages exist on disk but are not publicly
        // rendered either — the same as a page the frontmatter
        // restricts (mirrors the blocks export's public filter).
        if (preg_match('/^\s*draft\s*:\s*true\s*$/mi', $frontmatter) === 1) {
            return self::STATUS_STAGED;
        }

        return self::STATUS_PUBLIC;
    }
}
