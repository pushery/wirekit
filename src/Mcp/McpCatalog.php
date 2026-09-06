<?php

declare(strict_types=1);

namespace Pushery\WireKit\Mcp;

use Pushery\WireKit\ComponentRegistry;
use Pushery\WireKit\Support\AccessibilityContract;
use Pushery\WireKit\WireKit;

/**
 * Read-only catalog the MCP server exposes to AI coding assistants.
 *
 * It is sourced ENTIRELY from surfaces that ship in the Packagist tarball —
 * `ComponentRegistry` (props via the PropsParser-backed `extractProps`), the
 * compiled `dist/wirekit.css` design tokens, and the baked
 * `resources/mcp/component-examples.json`. It never re-implements prop parsing
 * (the PropsParser caller-drift guard forbids regex `@props` scanners; this
 * routes through the canonical registry instead).
 *
 * It reads nothing from `docs/` at RUNTIME, and that distinction is the whole
 * design of the examples: `docs/` is export-ignored, so it is absent in a real
 * `composer require` install, and a catalog that read it would answer correctly
 * in this repository and "no examples" in every installation — the worst shape
 * for a defect, because it cannot be reproduced where it is developed. The
 * examples are therefore extracted from the documentation at BUILD time into a
 * file that ships, and a test fails when the two drift apart.
 */
final class McpCatalog
{
    /**
     * The component catalog.
     *
     * Sub-components ride along on their parent's entry rather than as entries of
     * their own: they ARE part of the API an agent must reach for, but they are
     * not components in the sense the counts in this project use the word, and
     * listing them flat would inflate the component count by close to half without
     * one new component shipping. Naming them on the parent means an agent listing the
     * catalog SEES that card has a body — the thing it needs to know — and can
     * then ask for `card.body` directly.
     *
     * @return list<array{name: string, category: string, description: string, sub_components?: list<string>}>
     */
    public function components(): array
    {
        $out = [];
        foreach (ComponentRegistry::all() as $name => $meta) {
            $entry = [
                'name' => $name,
                'category' => $meta['category'] ?? 'Other',
                'description' => $meta['description'] ?? '',
            ];

            $subs = ComponentRegistry::subComponentsOf($name);

            if ($subs !== []) {
                $entry['sub_components'] = $subs;
            }

            $out[] = $entry;
        }

        return $out;
    }

    /**
     * Substring search across name / category / description.
     *
     * @return list<array{name: string, category: string, description: string}>
     */
    public function searchComponents(string $query, int $limit = 20): array
    {
        $query = trim(mb_strtolower($query));
        $limit = max(1, min($limit, 100));

        if ($query === '') {
            return array_slice($this->components(), 0, $limit);
        }

        $matches = array_values(array_filter(
            $this->components(),
            // The sub-component names are part of what a parent matches on: an
            // agent searching "card.body" or "th" should land on the component
            // that carries it rather than on nothing.
            static fn (array $c): bool => str_contains(mb_strtolower($c['name']), $query)
                || str_contains(mb_strtolower($c['category']), $query)
                || str_contains(mb_strtolower($c['description']), $query)
                || str_contains(mb_strtolower(implode(' ', $c['sub_components'] ?? [])), $query),
        ));

        return array_slice($matches, 0, $limit);
    }

    /**
     * Full detail for one component: metadata + the declared props (name,
     * default, and the inline allowed-value comment, which is exactly what an
     * editor wants for autocomplete).
     *
     * @return array{name: string, category: string, description: string, tag: string, props: list<array{name: string, default: ?string, comment: ?string}>}|null
     */
    public function getComponent(string $name): ?array
    {
        // resolve() answers for BOTH shapes — `card` and `card.body`. Asking only
        // for top-level components returned null for every sub-component, which an
        // agent reads as "no such component"; it then puts content directly into
        // <x-wirekit::card> instead of card.body — the exact mistake the shipped
        // AGENTS.md spends a paragraph preventing. Telling an agent to use a thing
        // and then denying it exists is the worst of both.
        $meta = ComponentRegistry::resolve($name);
        if ($meta === null) {
            return null;
        }

        // Every field the extractor produces, not a chosen three.
        //
        // This used to narrow to name/default/comment, which reads as tidy and is
        // a loss the caller cannot detect: `type_hint` is what tells an agent the
        // prop is an enum rather than free text, `default_normalized` is the
        // resolved value behind a `config(...)` call, and `examples` are the
        // values the docblock actually names. An agent given only the raw default
        // `config('wirekit.components.button.intent', 'primary')` has to guess
        // what may be passed — which is precisely the guessing this catalog
        // exists to remove.
        $props = ComponentRegistry::extractProps($name);

        $class = ComponentRegistry::componentClass($name);

        return [
            'name' => $name,
            'category' => $meta['category'] ?? 'Other',
            'description' => $meta['description'] ?? '',
            // Derive the tag from the registry — the interpolated "<x-wirekit::{name}>"
            // form is wrong for a class-based component like `chart`, whose real tag is
            // the hyphenated <x-wirekit-chart>.
            'tag' => ComponentRegistry::tag($name),
            'docs_url' => $this->docsUrl($name),
            // How the component exposes its API. An anonymous component takes
            // props AND named template slots; a class-based one takes constructor
            // arguments and has no developer-facing slots. An agent that knows
            // which it is generates the right wrapping shape instead of deriving
            // it from prop names.
            'component_kind' => $class !== null ? 'class' : 'anonymous',
            'props' => $props,
            'slots' => ComponentRegistry::slotsOf($name),
            'sub_components' => ComponentRegistry::describeSubComponentsOf($name),
        ] + (isset($meta['parent']) ? ['parent' => $meta['parent']] : []);
    }

    /**
     * Worked examples for one component: real, rendered usage a reader already
     * reviewed, rather than markup an assistant assembled from a prop list.
     *
     * Sub-components resolve to their PARENT's page, because that is where they
     * are documented — asking for `card.body` and being told "no examples"
     * would be true of the file layout and useless to the caller, who wants to
     * see a card body in a card.
     *
     * @return array{name: string, page: string, examples: list<array{title: string, code: string}>}|null
     *                                                                                                    null when the component does not exist at all — distinct from a
     *                                                                                                    real component that simply has no examples, which returns an empty list.
     */
    public function examples(string $name): ?array
    {
        $meta = ComponentRegistry::resolve($name);

        if ($meta === null) {
            return null;
        }

        // Three sources, most authoritative first.
        //
        // `parent` is what the registry states outright. The baked map covers the
        // case it does not: a component that carries NO parent and still has no
        // page of its own, because a family documents all of its primitives on
        // one page. Measured 2026-09-05 — sixteen of them, every `reading-*`
        // primitive among them, and the registry states a parent for none.
        //
        // Falling straight through to `$name` returned an empty list for all of
        // them, and an empty list is indistinguishable from "this component has
        // no worked examples". An agent told that builds markup from a prop list
        // instead of from usage a person already reviewed — the exact guessing
        // this catalog exists to remove.
        $page = is_string($meta['parent'] ?? null)
            ? $meta['parent']
            : ($this->componentPages()[$name] ?? $name);

        $baked = $this->bakedExamples();

        return [
            'name' => $name,
            'page' => $page,
            'examples' => $baked[$page] ?? [],
        ];
    }

    /**
     * Which page documents a component that has none of its own, read once per process.
     *
     * Baked for the same reason the examples are: `docs/` is export-ignored, so
     * the relationship is invisible in every installation. A missing file yields
     * an empty map and the lookup falls back to the component's own name — the
     * behavior before this map existed, which is degraded rather than broken.
     *
     * @return array<string, string>
     */
    private function componentPages(): array
    {
        if ($this->componentPagesCache !== null) {
            return $this->componentPagesCache;
        }

        $path = \dirname(__DIR__, 2).'/resources/mcp/component-pages.json';

        if (! is_file($path)) {
            return $this->componentPagesCache = [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return $this->componentPagesCache = is_array($decoded) ? $decoded : [];
    }

    /**
     * The baked example file, read once per process.
     *
     * A missing or unreadable file yields an empty map rather than an error: the
     * examples are an enrichment, and an agent that can still get prop
     * signatures out of a catalog with no examples is better served than one
     * that gets an exception.
     *
     * @return array<string, list<array{title: string, code: string}>>
     */
    private function bakedExamples(): array
    {
        if ($this->examplesCache !== null) {
            return $this->examplesCache;
        }

        $path = \dirname(__DIR__, 2).'/resources/mcp/component-examples.json';

        if (! is_file($path)) {
            return $this->examplesCache = [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return $this->examplesCache = is_array($decoded) ? $decoded : [];
    }

    /** @var array<string, list<array{title: string, code: string}>>|null */
    private ?array $examplesCache = null;

    /** @var array<string, string>|null */
    private ?array $componentPagesCache = null;

    /**
     * The component's documentation URL, or null when it has no publicly
     * rendered page.
     *
     * Read from a BAKED list, never from `docs/`, for the reason this whole
     * class is built around: `docs/` is export-ignored and simply absent in a
     * real installation, so a visibility check against it answers correctly here
     * and "nothing is public" everywhere else — a defect that cannot be
     * reproduced where it is developed.
     *
     * The examples map is deliberately not reused as that list. A public page
     * carrying no shippable preview is missing from it: measured 2026-08-18, the
     * examples map held 163 stems while 166 pages were public, and `map` was one
     * of the three. Deriving the URL from example membership would have nulled
     * exactly those, silently and only for the components with the thinnest
     * documentation — the ones an agent most needs the link for.
     */
    private function docsUrl(string $name): ?string
    {
        return in_array($name, $this->publicPages(), true)
            ? WireKit::DOCS_URL."/components/{$name}"
            : null;
    }

    /**
     * The baked public-page stems, read once per process.
     *
     * A missing file yields an empty list rather than an error, matching the
     * examples file: the catalog's prop signatures are worth more than its
     * links, and an agent that gets them with no URLs is better served than one
     * that gets an exception.
     *
     * @return list<string>
     */
    private function publicPages(): array
    {
        if ($this->publicPagesCache !== null) {
            return $this->publicPagesCache;
        }

        $path = \dirname(__DIR__, 2).'/resources/mcp/public-pages.json';

        if (! is_file($path)) {
            return $this->publicPagesCache = [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return $this->publicPagesCache = is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    /** @var list<string>|null */
    private ?array $publicPagesCache = null;

    /**
     * What one component has already wired for accessibility, and what its caller still owes it.
     *
     * A thin pass-through to the derivation in `AccessibilityContract` — the catalog is where an
     * assistant looks, and the derivation is where the reading of the shipped sources lives. Both
     * wrong answers to this question ship markup that looks right: a role added on top of one the
     * component already carries is two competing contracts on one element, and a name the
     * component waits for and never gets is an announced landmark that says nothing.
     *
     * @return array{
     *     component: string,
     *     roles: list<string>,
     *     aria: list<string>,
     *     caller_supplies: list<string>,
     *     keys: list<string>,
     * }|null
     */
    public function accessibility(string $name): ?array
    {
        return AccessibilityContract::for($name);
    }

    /**
     * Every `--*-wk-*` design token defined in the shipped `dist/wirekit.css`
     * `:root` block, as name → value pairs.
     *
     * @return list<array{name: string, value: string}>
     */
    public function tokens(): array
    {
        $cssPath = $this->distCssPath();
        if ($cssPath === null || ! is_file($cssPath)) {
            return [];
        }

        $css = (string) file_get_contents($cssPath);
        if (! preg_match_all('/^\s*(--[a-z0-9]+-wk-[a-z0-9-]+)\s*:\s*([^;]+);/m', $css, $m, PREG_SET_ORDER)) {
            return [];
        }

        $seen = [];
        $out = [];
        foreach ($m as $match) {
            $tokenName = $match[1];
            if (isset($seen[$tokenName])) {
                continue;
            }
            $seen[$tokenName] = true;
            $out[] = ['name' => $tokenName, 'value' => trim($match[2])];
        }

        return $out;
    }

    /**
     * Resolve the shipped `dist/wirekit.css`. From `src/Mcp/` the package root
     * is two levels up; falls back to the published-asset location so the
     * server also works when only published assets are present.
     */
    private function distCssPath(): ?string
    {
        $packageCss = \dirname(__DIR__, 2).'/dist/wirekit.css';
        if (is_file($packageCss)) {
            return $packageCss;
        }

        if (\function_exists('public_path')) {
            $published = public_path('vendor/wirekit/wirekit.css');
            if (is_file($published)) {
                return $published;
            }
        }

        return null;
    }
}
