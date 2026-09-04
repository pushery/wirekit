<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Pushery\WireKit\ComponentRegistry;
use Pushery\WireKit\Support\BladeParser;
use Pushery\WireKit\Support\DocsVisibility;
use Pushery\WireKit\Support\PropsParser;
use Pushery\WireKit\Support\SuggestSimilar;
use Pushery\WireKit\WireKit;

class ShowComponentCommand extends Command
{
    protected $signature = 'wirekit:show
        {name : Component name (e.g. button, modal)}
        {--as= : Output format. "json" emits the structured component schema (props/slots) as machine-readable JSON.}
        {--validate-against= : Path to a developer Blade file. Reads every <x-wirekit::name> usage in the file and warns when a passed attribute does NOT match a known prop name. Pre-runtime catch of "did I typo a prop?".}';

    protected $description = 'Show details for a WireKit component (props, category, usage)';

    public function handle(): int
    {
        $name = $this->argument('name');
        $meta = ComponentRegistry::get($name);

        // Accepts dotted sub-component
        // names — `wirekit:show card.body` / `wirekit:show timeline.item`.
        // Pre-fix the dotted form returned "Unknown component" because
        // ComponentRegistry tracks only top-level components. We now
        // resolve sub-components by reading the nested Blade file
        // directly and extracting props from it.
        if ($meta === null && str_contains($name, '.')) {
            return $this->handleSubComponent($name);
        }

        if ($meta === null) {
            $this->error("Unknown component: {$name}");

            // Uniform "Did you mean?" via Levenshtein distance — covers
            // single-char typos (`buttn` → `button`) AND dotted sub-names
            // (`card.bdy` → `card.body`). Wired through SuggestSimilar so
            // every CLI surface uses the same suggestion contract.
            $all = array_keys(ComponentRegistry::all());
            $hint = SuggestSimilar::format(
                SuggestSimilar::byLevenshtein($name, $all)
            );
            if ($hint !== null) {
                $this->line('  '.$hint);
            }

            return self::FAILURE;
        }

        // Machine-readable JSON output. Skips the human-
        // facing pretty output entirely; emits only the structured
        // schema to stdout so developers can pipe to `jq`.
        if ($this->option('as') === 'json') {
            return $this->emitJson($name, $meta);
        }
        if ($this->option('as') !== null && $this->option('as') !== '') {
            $this->error("Unknown --as format: {$this->option('as')}");
            $this->line('  Available: json (default = human-readable table)');

            return self::FAILURE;
        }

        // Validate-against mode. Reads the developer's
        // Blade file, finds every <x-wirekit::{name} ...> tag, checks
        // each attribute against the prop list. Emits warnings for
        // unknown attributes + the suggested closest prop.
        if ($this->option('validate-against') !== null) {
            return $this->validateAgainst($name, (string) $this->option('validate-against'));
        }

        $this->info("Component: {$name}");
        $this->line('');
        $this->line("  <fg=yellow>Category:</>    {$meta['category']}");
        $this->line("  <fg=yellow>Description:</> {$meta['description']}");
        $this->line('  <fg=yellow>Tag:</>         '.ComponentRegistry::tag($name));
        $this->line('');

        // Extract props from blade file — structured records with comment metadata.
        $props = ComponentRegistry::extractProps($name);

        if ($props !== []) {
            $this->line('  <fg=yellow>Props:</>');

            foreach ($props as $prop) {
                $default = $prop['default'] ?? '';
                $line = "    <fg=green>{$prop['name']}</> = {$default}";
                if ($prop['comment'] !== null && $prop['comment'] !== '') {
                    $line .= "  <fg=gray>// {$prop['comment']}</>";
                }
                $this->line($line);
            }
        } else {
            $this->line('  <fg=yellow>Props:</> (none or class-based)');
        }

        $this->line('');

        // Slots — named-slot quick forms (e.g. dropdown's <x-slot:trigger>) plus
        // the default slot. Uses the SAME source-of-truth extractor as
        // wirekit:export-json + the .wirekit-schema.json writer, so `show` no
        // longer omits the slot contract a developer needs to discover.
        $slots = $this->extractSlots($name);
        if ($slots !== []) {
            $this->line('  <fg=yellow>Slots:</>');
            foreach ($slots as $slot) {
                $req = $slot['required'] ? '<fg=red>(required)</>' : '<fg=gray>(optional)</>';
                $this->line("    <fg=green>{$slot['name']}</> {$req}");
            }
            $this->line('');
        }

        // Check for sub-components
        $subDir = __DIR__.'/../../resources/views/components/'.$name;
        if (is_dir($subDir)) {
            $subFiles = glob("{$subDir}/*.blade.php") ?: [];
            if ($subFiles !== []) {
                $this->line('  <fg=yellow>Sub-components:</>');
                foreach ($subFiles as $file) {
                    $subName = basename($file, '.blade.php');
                    if ($subName === 'index') {
                        continue;
                    }
                    $this->line("    <fg=green><x-wirekit::{$name}.{$subName}></>");
                }
                $this->line('');
            }
        }

        $docsUrl = $this->docsUrlFor($name);

        if ($docsUrl !== null) {
            $this->line('  <fg=yellow>Docs:</> '.$docsUrl);
        } else {
            // The catalog index rather than a guess at a page. It resolves, and it is
            // where a reader finds the parent this component is covered on.
            $this->line('  <fg=yellow>Docs:</> '.WireKit::DOCS_URL.'/components  <fg=gray>(no published page of its own)</>');
        }

        return self::SUCCESS;
    }

    /**
     * The published documentation URL for a component, or null when there is none.
     *
     * Both surfaces of this command printed the URL unconditionally, and sixteen
     * components have no page of their own — the sub-component pattern (toast-region,
     * glass, the reading-* family, kanban-column …), which is documented on a parent
     * page. So `wirekit:show glass` ended on a link to a 404 while `wirekit:export-json`
     * and the MCP catalog, asked the same question about the same component in the same
     * checkout, both answered null. Three surfaces, one fact, two answers.
     *
     * DocsVisibility is the oracle the exporters already route through, and routing this
     * one through it too is what makes the three agree by construction rather than by
     * everyone remembering. It answers correctly in an installed package as well, where
     * `docs/` is absent and a filesystem check would say "no page" for every component.
     */
    private function docsUrlFor(string $name): ?string
    {
        return DocsVisibility::componentPageStatus($name) === DocsVisibility::STATUS_PUBLIC
            ? WireKit::DOCS_URL."/components/{$name}"
            : null;
    }

    /**
     * Emit the structured schema for a component as JSON.
     *
     * Pure machine-readable output — no decoration, no `info()` lines.
     * Developers can pipe directly to `jq`. Same JSON-flags as
     * wirekit:export-json (XSS-safe, slash-preserving, unicode pass-through).
     *
     * @param  array{category: string, description: string}  $meta
     */
    private function emitJson(string $name, array $meta): int
    {
        $props = ComponentRegistry::extractProps($name);

        // One source for what sub-components exist. This used to be a local
        // filesystem walk, duplicated again in the JSON exporter and absent from
        // the MCP catalog — three surfaces answering the same question their own
        // way, which is how the MCP one ended up answering it with null.
        $subComponents = ComponentRegistry::subComponentsOf($name);

        $payload = [
            'name' => $name,
            'tag' => ComponentRegistry::tag($name),
            'category' => $meta['category'],
            'description' => $meta['description'],
            'docs_url' => $this->docsUrlFor($name),
            'props' => $props,
            'slots' => $this->extractSlots($name),
            'sub_components' => $subComponents,
        ];

        $this->output->write(
            (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)
        );

        return self::SUCCESS;
    }

    /**
     * Extract the slot schema for a top-level component, reusing the SAME
     * BladeParser source-of-truth as wirekit:export-json + the
     * .wirekit-schema.json writer (so `show` can never drift from them).
     * Resolves both the flat `{name}.blade.php` and the
     * `{name}/index.blade.php` anonymous-component layouts.
     *
     * @return list<array{name: string, required: bool}>
     */
    private function extractSlots(string $name): array
    {
        $base = __DIR__.'/../../resources/views/components';
        $bladePath = "{$base}/{$name}.blade.php";
        if (! is_file($bladePath)) {
            $index = "{$base}/{$name}/index.blade.php";
            if (! is_file($index)) {
                return [];
            }
            $bladePath = $index;
        }

        return BladeParser::extractSlotsWithMetadataFromSource(
            (string) file_get_contents($bladePath),
            $bladePath,
        );
    }

    /**
     * Validate every `<x-wirekit::{name} attr="..." />` usage in a developer
     * Blade file against the component's known prop set.
     *
     * Heuristic: tag-by-tag attribute scan against the prop names
     * extracted by PropsParser. Unknown attributes that don't match a
     * common Blade-passthrough (`class`, `style`, `id`, `wire:*`,
     * `x-*`, `@*`, `data-*`, `aria-*`) trigger a warning with the
     * closest matching prop name (Levenshtein-ranked).
     *
     * Where each tag ends comes from `BladeParser::tagsFromSource()`, and both reasons are
     * failures this validation used to have. It ran to the first `>`, which is ordinary
     * inside a value — `x-show="count > 3"` has one — so every attribute after it went
     * unchecked; and it read names across the whole tag body, so `count`, a word out of the
     * middle of that same value, was reported as a passed attribute. A developer wiring this
     * into a pre-commit hook got a warning about something they never wrote and none about
     * the typo they did.
     *
     * Exit code: 0 on clean validation, 1 on any unknown-attribute
     * warning. Lets developers wire `wirekit:show foo --validate-against=resources/views/page.blade.php`
     * into pre-commit hooks.
     */
    private function validateAgainst(string $name, string $developerBladePath): int
    {
        if (! file_exists($developerBladePath)) {
            $this->error("Developer Blade file not found: {$developerBladePath}");

            return self::FAILURE;
        }

        $content = (string) file_get_contents($developerBladePath);
        $props = ComponentRegistry::extractProps($name);
        $knownProps = array_map(fn ($p) => $p['name'], $props);

        $totalUsages = 0;
        $issues = [];

        foreach (BladeParser::tagsFromSource($content) as $tag) {
            // The exact component, not a prefix of one: `card` must not collect
            // `<x-wirekit::card.body>`, whose props are a different set entirely.
            if ($tag['name'] !== 'x-wirekit::'.$name) {
                continue;
            }

            // A tag another element interrupted never closed, so its attribute names are
            // whatever followed it in the file rather than anything the developer passed.
            if ($tag['terminator'] === '<') {
                continue;
            }

            $totalUsages++;
            $line = substr_count(substr($content, 0, $tag['start']), "\n") + 1;

            foreach ($tag['attributes'] as $attr) {
                // Strip Alpine-binding / wire / Livewire prefixes for
                // the prop-name comparison.
                $candidate = ltrim($attr, ':@');

                if (in_array($candidate, $knownProps, true)) {
                    continue;
                }

                // Allowlist common Blade / Alpine / Livewire attributes
                // that aren't WireKit props but are valid usage.
                if (preg_match('/^(class|style|id|slot|wire(:|$)|x-|data-|aria-|@|role|tabindex)/', $attr)) {
                    continue;
                }

                $issues[] = [
                    'line' => $line,
                    'attr' => $candidate,
                    'closest' => $this->closestProp($candidate, $knownProps),
                ];
            }
        }

        if ($totalUsages === 0) {
            $this->info("No <x-wirekit::{$name}> usages found in {$developerBladePath}");

            return self::SUCCESS;
        }

        $this->info("Scanned {$totalUsages} <x-wirekit::{$name}> usage(s) in {$developerBladePath}");
        $this->line('');

        if ($issues === []) {
            $this->info('  ✓ All passed attributes match known props.');

            return self::SUCCESS;
        }

        foreach ($issues as $issue) {
            $hint = $issue['closest'] !== null ? " — did you mean: <fg=cyan>{$issue['closest']}</>?" : '';
            $this->line("  <fg=yellow>⚠</> line {$issue['line']}: unknown attribute <fg=red>{$issue['attr']}</>{$hint}");
        }
        $this->line('');
        $this->line('  Run <fg=cyan>php artisan wirekit:show '.$name.'</> to see the full prop list.');

        return self::FAILURE;
    }

    /**
     * Find the closest match from $candidates to $needle by Levenshtein
     * distance. Returns null when no candidate is within distance 3.
     *
     * @param  list<string>  $candidates
     */
    private function closestProp(string $needle, array $candidates): ?string
    {
        $best = null;
        $bestDistance = 4;
        foreach ($candidates as $candidate) {
            $d = levenshtein($needle, $candidate);
            if ($d < $bestDistance) {
                $bestDistance = $d;
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * Handles dotted sub-component
     * names like `card.body`, `timeline.item`, `alert-dialog.cancel`.
     *
     * Resolves the parent component, then reads the nested Blade file
     * at `resources/views/components/{parent}/{child}.blade.php`.
     * Extracts props directly via PropsParser (the same source-of-truth
     * the ComponentRegistry uses for top-level components).
     *
     * Honors the --as=json option so AI tooling can introspect
     * sub-components the same way as top-level components.
     */
    private function handleSubComponent(string $name): int
    {
        [$parent, $child] = explode('.', $name, 2);
        $parentMeta = ComponentRegistry::get($parent);

        if ($parentMeta === null) {
            $this->error("Unknown component: {$parent} (resolving {$name})");

            return self::FAILURE;
        }

        $packageRoot = dirname(__DIR__, 2);
        $bladePath = $packageRoot."/resources/views/components/{$parent}/{$child}.blade.php";

        if (! is_file($bladePath)) {
            $this->error("Unknown sub-component: {$name}");
            $this->line("  Looked at: resources/views/components/{$parent}/{$child}.blade.php");

            // List sibling sub-components for a Did-you-mean hint.
            $siblings = $this->siblingSubComponents($parent, $packageRoot);
            if ($siblings !== []) {
                $hint = SuggestSimilar::format(
                    SuggestSimilar::byLevenshtein($child, $siblings)
                );
                if ($hint !== null) {
                    $this->line('  '.$hint);
                }
                $this->line('  Available: '.implode(', ', array_map(fn ($s) => "{$parent}.{$s}", $siblings)));
            }

            return self::FAILURE;
        }

        // Parse props from the nested Blade file.
        $props = PropsParser::parseBlade($bladePath);

        if ($this->option('as') === 'json') {
            $payload = [
                'name' => $name,
                'parent' => $parent,
                'child' => $child,
                'tag' => "<x-wirekit::{$name}>",
                'props' => $props,
                'sub_component' => true,
            ];
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info("Sub-component: {$name}");
        $this->line('');
        $this->line("  <fg=yellow>Parent:</>      {$parent}");
        $this->line("  <fg=yellow>Tag:</>         <x-wirekit::{$name}>");
        $this->line("  <fg=yellow>Blade:</>       resources/views/components/{$parent}/{$child}.blade.php");
        $this->line('');

        if ($props !== []) {
            $this->line('  <fg=yellow>Props:</>');
            foreach ($props as $prop) {
                $default = $prop['default'] ?? '';
                $line = "    <fg=green>{$prop['name']}</> = {$default}";
                if (! empty($prop['comment'])) {
                    $line .= " — {$prop['comment']}";
                }
                $this->line($line);
            }
        } else {
            $this->line('  <fg=yellow>Props:</> (slot-only, no declared @props)');
        }

        return self::SUCCESS;
    }

    /**
     * List the names of sibling sub-component blade files under a
     * parent's directory. Returns an empty array when the parent has
     * no sub-component directory.
     *
     * @return list<string>
     */
    private function siblingSubComponents(string $parent, string $packageRoot): array
    {
        $dir = "{$packageRoot}/resources/views/components/{$parent}";
        if (! is_dir($dir)) {
            return [];
        }

        $files = glob("{$dir}/*.blade.php") ?: [];

        return array_values(array_map(
            fn ($path) => basename($path, '.blade.php'),
            $files
        ));
    }
}
