<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Pushery\WireKit\ComponentRegistry;
use Pushery\WireKit\Support\BladeParser;
use Pushery\WireKit\Support\ClassPropsExtractor;
use Pushery\WireKit\Support\DocsVisibility;
use Pushery\WireKit\Support\PropsParser;
use Pushery\WireKit\Support\VersionResolver;
use Pushery\WireKit\WireKit;

/**
 * components.json export.
 *
 * Emits a machine-readable manifest of every WireKit component, including
 * category, description, props (parsed from @props([...]) blocks in the
 * Blade file), and slot names (parsed from $slot / @isset($slotName)
 * references). Designed to be consumed by the docs site's
 * /components.json endpoint, AI tooling, and design-system audits.
 *
 * The docs site's wrapper (BuildComponentsJsonCommand) calls us twice:
 * once with --pretty, once without. We support both by accepting --pretty
 * and pretty-printing whenever it's set; without the flag we emit
 * minified JSON. Either way: stdout-only, exit 0 on success, JSON
 * decodable.
 *
 * --public restricts the manifest to components whose dedicated docs
 * page is publicly rendered — the variant docs.wirekit.app serves at
 * /components.json. A component whose page is not publicly rendered is
 * omitted entirely. Components with NO dedicated page (the sub-component
 * pattern, documented on a parent page) always stay. The flagless full
 * manifest is the build input for docs.wirekit.app and internal tooling.
 *
 * Usage:
 *   php artisan wirekit:export-json --pretty
 *   php artisan wirekit:export-json --public
 *   php artisan wirekit:export-json
 *
 * Output: full JSON document on stdout. Exit code 0 = success.
 */
class ExportJsonCommand extends Command
{
    protected $signature = 'wirekit:export-json
        {--pretty : Pretty-print the JSON output}
        {--public : Emit the manifest docs.wirekit.app serves at its /components.json endpoint (the default emits the full inventory).}';

    protected $description = 'Emit machine-readable JSON manifest of every WireKit component (props + slots + category)';

    public function handle(): int
    {
        $components = [];

        foreach (ComponentRegistry::all() as $name => $meta) {
            $pageStatus = DocsVisibility::componentPageStatus($name);

            // --public: a component whose page exists but is not
            // publicly rendered is omitted ENTIRELY — never merely
            // docs_url=null. MISSING is deliberately kept: a page-less
            // sub-component (toast-region, glass, reading-*) is
            // documented on a parent page.
            if ($this->option('public') && $pageStatus === DocsVisibility::STATUS_STAGED) {
                continue;
            }

            $bladePath = $this->resolveBladePath($name);
            // ComponentRegistry::extractProps() is THE single source of
            // truth for prop extraction. It routes anonymous components
            // through PropsParser (reads @props([...])) and class-based
            // components (chart) through ClassPropsExtractor (Reflection
            // on the constructor signature). Both paths return the same
            // shape so this caller doesn't branch.
            $props = ComponentRegistry::extractProps($name);
            // Class-based components (chart) expose public properties (e.g.
            // `$alpineComponent`, `$chartConfig`, `$mountElement`) that the
            // Blade template references as `{{ $name }}` — without filtering
            // these out, BladeParser surfaces them as required `<x-slot:...>`
            // entries in the manifest. Pass the class's public-property names
            // as additional excludes so the emitted slots array reflects only
            // genuine template slots.
            $componentClass = ComponentRegistry::componentClass($name);
            $classPublicProps = $componentClass !== null
                ? ClassPropsExtractor::publicPropertyNames($componentClass)
                : [];
            $slots = $bladePath !== null ? $this->extractSlots($bladePath, $classPublicProps) : [];
            $subComponents = $this->describeSubComponents($name);

            // docs_url resolves to a publicly visitable page on
            // docs.wirekit.app. When the component's dedicated docs page
            // has no publicly-rendered surface, the field is null so AI
            // tooling clients don't fetch a URL that returns nothing
            // useful. In the FULL manifest the component entry remains
            // (it's still a callable Blade tag) with the URL nulled;
            // under --public a non-public entry was already dropped above.
            $docsUrl = $pageStatus === DocsVisibility::STATUS_PUBLIC
                ? WireKit::DOCS_URL."/components/{$name}"
                : null;

            $tagAlias = ComponentRegistry::tagAlias($name);
            $entry = [
                'name' => $name,
                'tag' => ComponentRegistry::tag($name),
            ];
            // for class-based
            // components whose canonical tag uses the single-hyphen
            // form (`<x-wirekit-chart>`), also emit the double-colon
            // alias (`<x-wirekit::chart>`) so tool integrators that
            // were grepping against the historical shape still match.
            // Normal anonymous components have no alias — field
            // omitted entirely in that case.
            if ($tagAlias !== null) {
                $entry['tag_alias'] = $tagAlias;
            }
            $entry['category'] = $meta['category'];
            $entry['description'] = $meta['description'];
            $entry['docs_url'] = $docsUrl;
            $entry['props'] = $props;
            // v2.4.0 Extension 4 — slot kind disambiguation. Downstream
            // LLM / IDE-extension tooling needs to know how a component
            // exposes its API: anonymous Blade components carry props
            // via @props([...]) blocks AND can accept named template
            // slots; class-based components carry props via constructor
            // signature reflection AND typically have NO developer-
            // facing template slots (their composition surface is
            // chart-class internals, not <x-slot:...> nesting).
            // The `component_kind` field on every manifest entry surfaces
            // this so a developer agent can generate the right wrapping
            // shape without re-deriving from prop names.
            $entry['component_kind'] = $componentClass !== null ? 'class' : 'anonymous';
            $components[] = $entry + [
                'slots' => $slots,
                'sub_components' => $subComponents,
            ];
        }

        $document = [
            'version' => $this->packageVersion(),
            'generated_at' => date('c'),
            'components' => $components,
        ];

        // JSON_HEX_TAG is non-negotiable — `/components.json` is consumed by
        // AI tooling and may be embedded in a <script type="application/ld+json">
        // block on a docs page. Without HEX_TAG, a description containing
        // `</script>` would break out of the surrounding script block. Same
        // contract as `wirekit:export-api-map` and `wirekit:export-blocks`.
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG;
        if ($this->option('pretty')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $json = json_encode($document, $flags);
        if ($json === false) {
            $this->error('Failed to encode component manifest as JSON: '.json_last_error_msg());

            return self::FAILURE;
        }

        // Write to stdout — the docs site captures whatever we emit and
        // serves it from /components.json. Use $this->line() with empty
        // verbosity guard so the JSON stays the only thing on stdout.
        $this->output->write($json);
        $this->output->writeln('');

        return self::SUCCESS;
    }

    /**
     * Locate the Blade file for a component by name.
     * Handles flat names (button.blade.php) AND dotted sub-component names
     * (card.header → card/header.blade.php).
     */
    private function resolveBladePath(string $name): ?string
    {
        $base = __DIR__.'/../../resources/views/components/';

        // Flat name first
        $flat = $base.$name.'.blade.php';
        if (file_exists($flat)) {
            return $flat;
        }

        // Dotted sub-component → directory + file
        $dotted = $base.str_replace('.', '/', $name).'.blade.php';
        if (file_exists($dotted)) {
            return $dotted;
        }

        return null;
    }

    /**
     * Parse named-slot references from a Blade file. Returns the
     * metadata-rich shape (`list<array{name, required}>`) per slot so
     * the schema can flag required slots — popover / hover-card /
     * context-menu's `trigger` slot, for example. Without the
     * `required` boolean, the manifest reports them as default-slot
     * only and silently hides the bug class where developers omit the
     * trigger and get `Undefined variable $trigger`.
     *
     * @return list<array{name: string, required: bool}>
     */
    /**
     * @param  list<string>  $additionalExcludes  Class-side public-property
     *                                            names for class-based
     *                                            components. Empty for
     *                                            anonymous Blade components.
     */
    private function extractSlots(string $bladePath, array $additionalExcludes = []): array
    {
        $contents = (string) file_get_contents($bladePath);

        return BladeParser::extractSlotsWithMetadataFromSource($contents, $bladePath, $additionalExcludes);
    }

    /**
     * Discover sub-components by scanning the sibling directory
     * `resources/views/components/<name>/`. Skips `index.blade.php`
     * (Laravel's anonymous-component index file — the parent's own
     * default render path, not a separate sub-component).
     *
     * Returns dot-separated qualified names (e.g. `card.body`,
     * `dropdown.item`, `modal.footer`) so AI / IDE tooling can match
     * them against `<x-wirekit::parent.child>` usage patterns.
     *
     * @return list<string>
     */
    /**
     * The parent's sub-components, each with the props it actually declares.
     *
     * Before: a list of bare name strings. That told a tool the name existed and
     * nothing else — so `table.th`'s `headerScope`, shipped and documented in
     * 2.16.0, was unreachable through the manifest, `.wirekit-schema.json`, and
     * every tool fed by them. A name without its props is not discovery; it is a
     * pointer to documentation the tool cannot read.
     *
     * Discovery itself lives in ComponentRegistry. It used to live here AND in
     * the show command AND nowhere in the MCP catalog — three answers to one
     * question, which is how the MCP surface ended up simply not having one.
     *
     * @return list<array{name: string, tag: string, props: list<array<string, mixed>>}>
     */
    private function describeSubComponents(string $name): array
    {
        $out = [];

        foreach (ComponentRegistry::subComponentsOf($name) as $sub) {
            $out[] = [
                'name' => $sub,
                'tag' => "<x-wirekit::{$sub}>",
                'props' => ComponentRegistry::extractProps($sub),
            ];
        }

        return $out;
    }

    /**
     * Resolve the running WireKit version. Delegates to VersionResolver so
     * `wirekit:export-json` / `wirekit:export-api-map` / `wirekit:export-blocks`
     * stay in lockstep — see VersionResolver for the priority order.
     */
    private function packageVersion(): string
    {
        return VersionResolver::resolve();
    }
}
