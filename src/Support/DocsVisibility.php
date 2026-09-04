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
     *
     * `docs/` is export-ignored, so it is simply ABSENT from an installed
     * package — and a status read against a directory that is not there
     * answers MISSING for every component. That answer is right in a
     * checkout and wrong everywhere the package actually runs: the
     * project-root `.wirekit-schema.json` and every `wirekit:export-json`
     * run in a real installation carried a null documentation URL for all
     * of them, while the MCP catalog, which reads a baked list instead of
     * the tree, answered correctly. Two shipped surfaces disagreed about
     * one fact, and the one a developer commits into their own repository
     * was the wrong one. It cannot be reproduced where it is developed,
     * because there the tree is right here.
     *
     * So the tree stays authoritative wherever it exists, and the baked
     * stem lists answer where it does not. They are extracted FROM that
     * tree, and the two are held in lockstep by a test rather than by
     * hand.
     */
    public static function componentPageStatus(string $name): string
    {
        $root = dirname(__DIR__, 2);

        if (is_dir($root.'/docs/components')) {
            return self::pageStatus($root."/docs/components/{$name}.md");
        }

        return self::bakedPageStatus($root, $name);
    }

    /**
     * The installed-package answer: the baked stem lists in
     * `resources/mcp/`, which ship because `docs/` does not.
     *
     * BOTH lists are consulted, and they are not the same question. A
     * stem on the public list has a page to advertise. A stem on the
     * non-public one has a page that is not publicly rendered, and a
     * public manifest drops it entirely — the name alone would announce
     * something unannounced. Everything else has no page of its own,
     * which is the sub-component pattern documented on a parent page, and
     * keeps its entry with a null URL. Collapsing those last two into one
     * answer goes wrong in whichever direction it is collapsed: one way
     * advertises a name that is not ready, the other deletes sixteen real
     * components from the manifest they belong in.
     *
     * A list that is missing or unreadable contributes nothing rather
     * than raising: a null documentation URL is a poor answer, and an
     * exception from inside an export command is a worse one.
     */
    private static function bakedPageStatus(string $root, string $name): string
    {
        if (in_array($name, self::bakedStems($root.'/resources/mcp/public-pages.json'), true)) {
            return self::STATUS_PUBLIC;
        }

        if (in_array($name, self::bakedStems($root.'/resources/mcp/staged-pages.json'), true)) {
            return self::STATUS_STAGED;
        }

        return self::STATUS_MISSING;
    }

    /**
     * One baked stem list, read once per file per process — an export
     * walks the whole registry, so this is asked ~180 times per run.
     *
     * @return list<string>
     */
    private static function bakedStems(string $path): array
    {
        static $cache = [];

        if (isset($cache[$path])) {
            return $cache[$path];
        }

        if (! is_file($path)) {
            return $cache[$path] = [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return $cache[$path] = is_array($decoded)
            ? array_values(array_filter($decoded, 'is_string'))
            : [];
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
