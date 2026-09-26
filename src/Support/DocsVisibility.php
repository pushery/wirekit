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
     *
     * A component with no page of its own is as public as the page that
     * documents it. One taught only on a page that is not publicly rendered
     * answers STAGED, from the baked `staged-components.json`, in the
     * checkout and in an installation alike: a public manifest drops it with
     * its parent instead of publishing its whole API while the parent is
     * unannounced.
     */
    public static function componentPageStatus(string $name): string
    {
        $root = dirname(__DIR__, 2);

        $status = is_dir($root.'/docs/components')
            ? self::pageStatus($root."/docs/components/{$name}.md")
            : self::bakedPageStatus($root, $name);

        if ($status === self::STATUS_MISSING
            && in_array($name, self::bakedStems($root.'/resources/mcp/staged-components.json'), true)) {
            return self::STATUS_STAGED;
        }

        return $status;
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

        // No frontmatter → the downstream Markdown parser applies its own defaults,
        // which are unrestricted and non-draft → publicly rendered.
        if (! str_starts_with($content, '---')) {
            return self::STATUS_PUBLIC;
        }

        $closing = strpos($content, "\n---", 3);
        if ($closing === false) {
            return self::STATUS_PUBLIC;
        }

        $frontmatter = substr($content, 3, $closing - 3);

        // `visibility:` value on its own line. Strict about the KEY, so a prose mention of
        // the field name is not mistaken for a declaration; tolerant about the VALUE,
        // because the failure directions are not symmetric — see readFrontmatterValue().
        $visibility = self::readFrontmatterValue($frontmatter, 'visibility');

        if ($visibility !== null && strtolower($visibility) !== 'guest') {
            return self::STATUS_STAGED;
        }

        // `draft: true` pages exist on disk but are not publicly
        // rendered either — the same as a page the frontmatter
        // restricts (mirrors the blocks export's public filter).
        $draft = self::readFrontmatterValue($frontmatter, 'draft');

        if ($draft !== null && in_array(strtolower($draft), ['true', 'yes', 'on', '1'], true)) {
            return self::STATUS_STAGED;
        }

        return self::STATUS_PUBLIC;
    }

    /**
     * One frontmatter scalar, read the way YAML would write it.
     *
     * ⚠️ THE VALUE CLASS USED TO BE `([a-z]+)`, AND THAT IS A FAIL-OPEN SHAPE. Four ordinary
     * YAML spellings did not match it, and a non-match here does not mean "no restriction" —
     * it means the restriction was not SEEN, and the page was reported publicly renderable.
     * Written with a placeholder value, because naming the tiers in a source comment is
     * itself a leak this package forbids:
     *
     *     key: "value"           quoted — the quotes are outside [a-z]
     *     key: value # a note    an inline comment breaks the `\s*$` anchor
     *     key: value-with-dash   a hyphen is outside [a-z]
     *     draft: "true"          the same, on the other key
     *
     * The two directions cost differently, which is why the tolerance goes here rather than
     * being argued about case by case. Reading a restriction that is not there hides a page
     * from a manifest — a missing entry somebody notices. MISSING a restriction that IS
     * there publishes a restricted page in a public artifact, which the artifact rules call
     * an absolute ground rule. So the key stays strict and the value gets read properly.
     */
    private static function readFrontmatterValue(string $frontmatter, string $key): ?string
    {
        if (preg_match('/^\s*'.preg_quote($key, '/').'\s*:\s*(.+)$/mi', $frontmatter, $m) !== 1) {
            return null;
        }

        $value = trim($m[1]);

        // An inline comment. Only when it follows whitespace or starts the value — a `#`
        // inside a word (a color, a fragment) is part of the value.
        $value = (string) preg_replace('/(^|\s)#.*$/', '', $value);
        $value = trim($value);

        // Surrounding quotes, either style, only when they match each other.
        if (strlen($value) >= 2
            && ($value[0] === '"' || $value[0] === "'")
            && $value[strlen($value) - 1] === $value[0]) {
            $value = substr($value, 1, -1);
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
