<?php

declare(strict_types=1);

namespace Pushery\WireKit\Mcp;

use Pushery\WireKit\ComponentRegistry;
use Pushery\WireKit\Console\MakeCommand;
use Pushery\WireKit\Support\AccessibilityContract;
use Pushery\WireKit\Theming\ThemePresetRegistry;
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
        // a loss the caller cannot detect: `type_hint` carries the declared PHP
        // type of a class-based component's constructor argument,
        // `default_normalized` is the same expression as `default` with whitespace
        // collapsed and comments stripped so two records can be compared as
        // strings, and `examples` are the values an `@example` annotation names.
        // Dropping any of them narrows what the caller can answer, and says
        // nothing about having done so.
        //
        // ⚠️ Two of those clauses read the other way here, and on the public
        // `docs/ai-tooling.md` page, for a long series of releases: a type hint
        // that identifies an enum, and "the resolved value behind a `config(...)`
        // call". Neither is produced, and the payload says so plainly to anyone
        // who looks — measured 2026-09-06 over a real `--public` export:
        // `default_normalized` is byte-identical to `default` for all 139
        // config-backed defaults, and `type_hint` is null for 1282 of 1297 props,
        // because an anonymous component's `@props` block declares no types.
        //
        // Resolving the fallback literal is worth doing and would need a NEW
        // field: `default_normalized`'s whitespace-collapse semantics are
        // published in `docs/extending/component-registry.md` and pinned by
        // `PropsParserTest`, so repurposing it breaks a documented contract.
        // `McpPropFieldClaimsTest` now holds every such sentence to what the
        // extractor really returns, in both directions.
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
     * The bundled theme presets, read from the registry that `wirekit:theme` writes from.
     *
     * ⚠️ FROM `ThemePresetRegistry`, NEVER FROM THE DOCUMENTATION, AND THAT IS NOT A STYLE
     * PREFERENCE. A preset already reaches a developer by three artifacts — the registry the
     * command writes, the page they copy by hand, and the live picker on the documentation
     * site — and those three have measurably disagreed (`ThemePresetDocsValueDriftTest` holds
     * the surviving divergences as a ratchet). A fourth retelling would be a fourth thing to
     * hold in step. Reading the registry makes this view current by construction: it cannot
     * drift from the command, because it IS the command's source.
     *
     * @return list<array{key: string, label: string, has_dark_block: bool, command: string}>
     */
    public function presets(): array
    {
        $out = [];

        foreach (ThemePresetRegistry::all() as $key => $preset) {
            $out[] = [
                'key' => $key,
                'label' => (string) $preset['label'],
                'has_dark_block' => ($preset['dark_vars'] ?? null) !== null && $preset['dark_vars'] !== '',
                'command' => "php artisan wirekit:theme {$key}",
            ];
        }

        return $out;
    }

    /**
     * One preset with the CSS the command appends to `app.css`.
     *
     * `dark_vars` is null for most presets and that is meaningful rather than missing: those
     * presets inherit dark mode from the bundled defaults. It is reported as null rather than
     * as an empty string so the two states stay distinguishable.
     *
     * @return array{key: string, label: string, has_dark_block: bool, command: string, vars: string, dark_vars: string|null}|null
     */
    public function preset(string $key): ?array
    {
        if (! ThemePresetRegistry::isValid($key)) {
            return null;
        }

        $preset = ThemePresetRegistry::get($key);
        $dark = $preset['dark_vars'] ?? null;

        return [
            'key' => $key,
            'label' => (string) $preset['label'],
            'has_dark_block' => $dark !== null && $dark !== '',
            'command' => "php artisan wirekit:theme {$key}",
            'vars' => (string) $preset['vars'],
            'dark_vars' => $dark === null || $dark === '' ? null : (string) $dark,
        ];
    }

    /**
     * The recipe library — the composed page shapes `wirekit:make recipe:<name>` scaffolds.
     *
     * ⚠️ NOTHING HERE READS `docs/`, AND THE RECIPES ARE THE CASE WHERE THAT IS EASY TO GET
     * WRONG. Each recipe has a documentation page under `docs/blueprints/recipes/`, which is
     * the obvious place to read a title and a summary from — and it is export-ignored, so a
     * catalog built that way answers in this repository and returns eleven blanks in every
     * real install. The stubs under `src/Console/stubs/recipes/` ship, carry the same header,
     * and are the file the developer actually receives.
     *
     * The names come from `MakeCommand::RECIPES`, so the list an agent sees and the list the
     * command accepts cannot disagree.
     *
     * @return list<array{name: string, title: string, summary: string, docs_url: string, command: string}>
     */
    public function recipes(): array
    {
        $out = [];

        foreach (MakeCommand::RECIPES as $name) {
            $meta = $this->recipeHeader($name);

            if ($meta === null) {
                continue;
            }

            $out[] = $meta;
        }

        return $out;
    }

    /**
     * One recipe, with the Blade source the scaffold writes.
     *
     * @return array{name: string, title: string, summary: string, docs_url: string, command: string, source: string}|null
     */
    public function recipe(string $name): ?array
    {
        if (! in_array($name, MakeCommand::RECIPES, true)) {
            return null;
        }

        $meta = $this->recipeHeader($name);
        $path = $this->recipeStubPath($name);

        if ($meta === null || $path === null) {
            return null;
        }

        return $meta + ['source' => rtrim((string) file_get_contents($path))."\n"];
    }

    /**
     * Title, summary and docs URL, read out of the stub's own header comment.
     *
     * Every stub opens with the same three-line block — `{{-- Recipe: <Title> — <summary>`
     * then a `Full reference:` URL — so the metadata lives next to the code it describes
     * rather than in a table beside it. A stub whose header does not parse is skipped rather
     * than guessed at, and a test fails on it: a recipe listed with an empty summary reads as
     * a recipe that has nothing to say.
     *
     * @return array{name: string, title: string, summary: string, docs_url: string, command: string}|null
     */
    private function recipeHeader(string $name): ?array
    {
        $path = $this->recipeStubPath($name);

        if ($path === null) {
            return null;
        }

        $head = (string) file_get_contents($path);

        // The em dash is the separator the stubs use; a hyphen inside a title must not split it.
        if (preg_match('/\{\{--\s*Recipe:\s*(.+?)\s+\x{2014}\s+(.+?)\.?\s*$/mu', $head, $m) !== 1) {
            return null;
        }

        preg_match('#Full reference:\s*(https://\S+)#', $head, $url);

        return [
            'name' => $name,
            'title' => trim($m[1]),
            'summary' => trim($m[2]),
            'docs_url' => $url[1] ?? '',
            'command' => "php artisan wirekit:make recipe:{$name}",
        ];
    }

    /** The shipped stub for one recipe, or null when it is absent. */
    private function recipeStubPath(string $name): ?string
    {
        $path = \dirname(__DIR__, 2)."/src/Console/stubs/recipes/{$name}.blade.php";

        return is_file($path) ? $path : null;
    }

    /**
     * The house conventions, as shipped prose rather than as a list held here.
     *
     * ⚠️ THIS CLOSES A GAP `AGENTS.md` NAMES IN ITS OWN WORDS. That file tells an
     * assistant that "for an MCP-native editor, the WireKit MCP server exposes the same
     * CATALOG as live tools" — the catalog, not the conventions. So an agent on the MCP
     * path could read every prop of every component and never learn that a Tailwind
     * palette class, a `dark:` prefix or a hand-written color will fail this project's
     * guards. Cursor users got those rules from `.cursor/rules/wirekit.mdc`; an MCP
     * client has no filesystem and reached neither file.
     *
     * Nothing is authored here on purpose. Both documents already ship, and a third
     * copy of the same rules is a third thing to keep in step — the failure this whole
     * class is arranged against. Read at call time, so the answer is whatever the
     * installed version says.
     *
     * @return array{source: string, format: string, text: string}|null
     */
    public function conventions(bool $detailed = false): ?array
    {
        // Both files survive `git archive` — verified, and pinned by a test, because the
        // opposite is this class's signature defect: a source that is present in the
        // repository and absent in a real `composer require` install answers correctly
        // where it is developed and says nothing where it is used.
        $relative = $detailed ? '.cursor/rules/wirekit.mdc' : 'AGENTS.md';
        $path = \dirname(__DIR__, 2).'/'.$relative;

        if (! is_file($path)) {
            return null;
        }

        $text = (string) file_get_contents($path);

        // The `.mdc` frontmatter is Cursor's file-glob configuration. It tells an editor
        // which files the rules attach to and tells an agent over MCP nothing at all, so
        // it is dropped rather than shipped as noise the reader has to skip.
        $text = (string) preg_replace('/\A---\R.*?\R---\R+/s', '', $text);

        return [
            'source' => $relative,
            'format' => 'markdown',
            'text' => trim($text),
        ];
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
