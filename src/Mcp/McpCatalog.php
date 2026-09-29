<?php

declare(strict_types=1);

namespace Pushery\WireKit\Mcp;

use Pushery\WireKit\ComponentRegistry;
use Pushery\WireKit\Console\MakeCommand;
use Pushery\WireKit\Support\AccessibilityContract;
use Pushery\WireKit\Support\ComponentTokens;
use Pushery\WireKit\Support\DocsVisibility;
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
     * @param  bool  $publicOnly  Leave out every component whose documentation is not public yet.
     *                            On by default, because every reader of this catalog in a real
     *                            installation (the MCP server, the Boost manifest) hands its
     *                            answers to a developer's assistant. Off only where the whole
     *                            registry is the question, such as a check over every
     *                            component's props.
     */
    public function __construct(private readonly bool $publicOnly = true) {}

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
            if ($this->isStaged($name)) {
                continue;
            }

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

        // Every word is its own needle, and the whole query is one more. The whole query
        // alone is fine for `card.body` and useless for the way the search is actually
        // reached: somebody who knows the name does not need to search, and somebody who does
        // not know it types synonyms, so `alert danger error box callout` has to find both
        // `alert` and `callout`. An empty answer there reads as "no such component", and the
        // next step is writing the markup by hand.
        //
        // An empty answer is indistinguishable from "no such component", so the failure looks
        // like a fact about the library rather than about the query.
        $terms = array_values(array_unique(array_filter(preg_split('/\s+/', $query) ?: [])));

        $scored = [];

        foreach ($this->components() as $component) {
            $name = mb_strtolower($component['name']);
            $category = mb_strtolower($component['category']);
            $description = mb_strtolower($component['description']);
            // The sub-component names are part of what a parent matches on: an agent searching
            // "card.body" or "th" should land on the component that carries it.
            $subs = mb_strtolower(implode(' ', $component['sub_components'] ?? []));

            $score = 0;

            // The whole query as one phrase still counts, and counts heavily -- that is what
            // makes `card.body` land on `card` rather than on everything containing "card".
            if (str_contains($name, $query) || str_contains($description, $query) || str_contains($subs, $query)) {
                $score += 8;
            }

            foreach ($terms as $term) {
                // An exact name is the strongest signal there is. Without this `alert` ranked
                // `alert-dialog` first, so a search for a message box led to the confirmation
                // dialog -- reported alongside the empty-result defect.
                if ($name === $term) {
                    $score += 100;

                    continue;
                }

                if (str_contains($name, $term)) {
                    $score += 10;
                } elseif (str_contains($subs, $term)) {
                    $score += 4;
                } elseif (str_contains($description, $term)) {
                    $score += 3;
                } elseif (str_contains($category, $term)) {
                    $score += 1;
                }
            }

            if ($score > 0) {
                $scored[] = ['score' => $score, 'component' => $component];
            }
        }

        // Ranked by score, then by name so a tie is stable rather than left to sort order --
        // an agent that runs the same query twice should get the same answer twice.
        usort(
            $scored,
            static fn (array $a, array $b): int => $b['score'] <=> $a['score']
                ?: strcmp($a['component']['name'], $b['component']['name']),
        );

        return array_slice(array_column($scored, 'component'), 0, $limit);
    }

    /**
     * Full detail for one component: metadata + the declared props (name,
     * default, and the inline allowed-value comment, which is exactly what an
     * editor wants for autocomplete).
     *
     * @return array{
     *     name: string,
     *     category: string,
     *     description: string,
     *     tag: string,
     *     docs_url: ?string,
     *     component_kind: 'anonymous'|'class',
     *     props: list<array{name: string, default: ?string, default_normalized: ?string, type_hint: ?string, comment: ?string, examples: list<string>, values: ?list<string>, value_type: ?string}>,
     *     slots: list<array{name: string, required: bool}>,
     *     sub_components: list<array{name: string, tag: string, props: list<array<string, mixed>>, tokens: list<string>}>,
     *     tokens: list<string>,
     *     parent?: string,
     * }|null
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
        if ($meta === null || $this->isStaged($name)) {
            return null;
        }

        // Every field the extractor produces, not a chosen three.
        //
        // Narrowing to name/default/comment reads as tidy and is a loss the
        // caller cannot detect: `type_hint` carries the declared PHP
        // type of a class-based component's constructor argument,
        // `default_normalized` is the same expression as `default` with whitespace
        // collapsed and comments stripped so two records can be compared as
        // strings, and `examples` are the values an `@example` annotation names.
        // Dropping any of them narrows what the caller can answer, and says
        // nothing about having done so.
        //
        // Neither field resolves anything: `type_hint` does not identify an enum,
        // and `default_normalized` is not the value behind a `config(...)` call. An
        // anonymous component's `@props` block declares no types, so `type_hint` is
        // null for almost every prop.
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
            'tokens' => ComponentRegistry::tokensOf($name),
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

        if ($meta === null || $this->isStaged($name)) {
            return null;
        }

        // Three sources, most authoritative first.
        //
        // `parent` is what the registry states outright. The baked map covers the
        // case it does not: a component that carries NO parent and still has no
        // page of its own, because a family documents all of its primitives on
        // one page. The `reading-*` primitives are among them, and the registry
        // states a parent for none.
        //
        // Falling straight through to `$name` would return an empty list for all
        // of them, and an empty list is indistinguishable from "this component has
        // no worked examples". An agent told that builds markup from a prop list
        // instead of from usage a person already reviewed — the exact guessing
        // this catalog exists to remove.
        $page = is_string($meta['parent'] ?? null)
            ? $meta['parent']
            : ($this->componentPages()[$name] ?? $name);

        $baked = $this->bakedExamples();

        // A component a family documents on its page answers with the previews on that page
        // that render IT. The page's own examples are its first three, the family's head:
        // `reading-meta` answered with the shell and the progress bar and never with itself.
        $own = is_string($meta['parent'] ?? null) ? null : ($this->pagelessExamples()[$name] ?? null);

        return [
            'name' => $name,
            'page' => $page,
            'examples' => $own ?? ($baked[$page] ?? []),
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

    /**
     * The baked examples of the components documented on a family page, each one rendering
     * its component, read once per process. Missing or unreadable, it yields an empty map and
     * the lookup falls back to the page's own examples.
     *
     * @return array<string, list<array{title: string, code: string}>>
     */
    private function pagelessExamples(): array
    {
        if ($this->pagelessExamplesCache !== null) {
            return $this->pagelessExamplesCache;
        }

        $path = \dirname(__DIR__, 2).'/resources/mcp/pageless-examples.json';

        if (! is_file($path)) {
            return $this->pagelessExamplesCache = [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return $this->pagelessExamplesCache = is_array($decoded) ? $decoded : [];
    }

    /** @var array<string, list<array{title: string, code: string}>>|null */
    private ?array $examplesCache = null;

    /** @var array<string, list<array{title: string, code: string}>>|null */
    private ?array $pagelessExamplesCache = null;

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
     * carrying no shippable preview is missing from it, and deriving the URL from
     * example membership would null exactly those, silently and only for the components with the thinnest
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
     *     tokens: list<string>,
     * }|null
     */
    public function accessibility(string $name): ?array
    {
        if ($this->isStaged($name)) {
            return null;
        }

        return AccessibilityContract::for($name);
    }

    /**
     * A component whose documentation is not public yet, or one of its parts.
     *
     * The catalog answers `wirekit://catalog`, `list_components` and every lookup by name, and it
     * feeds the Boost manifest, so everything here reaches a developer's assistant. A staged
     * component is left out of every answer rather than listed with a null URL, the rule
     * `wirekit:export-json --public` applies: its name alone would announce something that has
     * not been announced. A part (`cart-list.item`) is as public as the component it belongs to.
     */
    private function isStaged(string $name): bool
    {
        if (! $this->publicOnly) {
            return false;
        }

        $component = explode('.', $name, 2)[0];

        return DocsVisibility::componentPageStatus($component) === DocsVisibility::STATUS_STAGED;
    }

    /**
     * Every design token the shipped `dist/wirekit.css` declares, as name → value pairs.
     *
     * The shape of the name is not part of the question. The stylesheet writes tokens as
     * `--<segment>-wk-<rest>`, `--<segment>-wk` (`--radius-wk`, the base every radius derives
     * from), `--wk-<segment>`, and the whole `--reading-*` family, which carries no `wk` at all
     * and is the largest documented per-component customization surface there is. So a custom
     * property declared on a line of its own in the shipped stylesheet is a token, because that
     * file is what a developer overrides.
     *
     * The reader is `ComponentTokens::declaredIn()`, the same one every component's `tokens` list
     * is drawn from, so this catalog and the manifest cannot disagree about which properties are
     * tokens.
     *
     * @return list<array{name: string, value: string}>
     */
    public function tokens(): array
    {
        $cssPath = $this->distCssPath();
        if ($cssPath === null || ! is_file($cssPath)) {
            return [];
        }

        $out = [];
        foreach (ComponentTokens::declaredIn((string) file_get_contents($cssPath)) as $tokenName => $value) {
            $out[] = ['name' => $tokenName, 'value' => $value];
        }

        return $out;
    }

    /**
     * The bundled theme presets, read from the registry that `wirekit:theme` writes from.
     *
     * From `ThemePresetRegistry`, never from the documentation. A preset already reaches a
     * developer by three artifacts — the registry the command writes, the page they copy by
     * hand, and the live picker on the documentation site — and a fourth retelling would be a
     * fourth thing to hold in step. Reading the registry makes this view current by
     * construction: it cannot drift from the command, because it is the command's source.
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
     * Nothing here reads `docs/`, and the recipes are the case where that is easy to get
     * wrong. Each recipe has a documentation page under `docs/blueprints/recipes/`, which is
     * the obvious place to read a title and a summary from — and it is export-ignored, so a
     * catalog built that way answers in this repository and returns a blank for every recipe
     * in a real install. The stubs under `src/Console/stubs/recipes/` ship, carry the same header,
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
     * Every stub opens with the same TWO lines — `{{-- Recipe: <Title> — <summary>` and a
     * `Full reference:` URL — so the metadata lives next to the code it describes rather than
     * in a table beside it. Whatever follows inside the comment is per-stub guidance for the
     * developer who just scaffolded the view, and this parser ignores it: some stubs carry a
     * line of it, some a paragraph, some none.
     *
     * What the parser needs is the `Recipe:` line with its em dash and, separately, a
     * `Full reference:` URL anywhere in the file. The header can be two lines or five.
     *
     * A stub whose header does not parse is skipped rather than guessed at, and a test fails
     * on it: a recipe listed with an empty summary reads as a recipe that has nothing to say.
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
     * `AGENTS.md` tells an assistant that "for an MCP-native editor, the WireKit MCP server
     * exposes the same CATALOG as live tools" — the catalog, not the conventions. This carries
     * the conventions to the MCP path, so an agent there learns that a Tailwind palette class,
     * a `dark:` prefix or a hand-written color will fail the guards: an MCP client has no
     * filesystem, and reads neither `AGENTS.md` nor `.cursor/rules/wirekit.mdc`.
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
