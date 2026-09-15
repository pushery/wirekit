<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
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

        /*
         * `--as` and `--validate-against` are two different jobs, and the flag order in this
         * method used to decide which one silently won.
         *
         * `--as=json` prints a schema and exits 0. `--validate-against` reads a developer's
         * Blade file and exits 1 on a finding — it is sold in the signature for pre-commit
         * hooks. Passed together, the `--as` branch below returned first, so the hook printed
         * a schema, validated nothing, and reported success. A linter that answers "clean" on
         * a file it never opened is worse than one that is missing.
         *
         * Rejected rather than resolved by precedence: either choice would be a guess about
         * which flag the developer meant, and a guess that exits 0 is the same failure with
         * an extra step. Exit 1 for a usage error is the catalog's convention.
         */
        if ($this->option('validate-against') !== null && (string) $this->option('as') !== '') {
            $this->error('--as and --validate-against cannot be combined.');
            $this->line('  --as prints the component schema; --validate-against lints a Blade file and exits 1 on a finding.');
            $this->line('  Run them as two commands.');

            return self::FAILURE;
        }

        // Machine-readable JSON output. Skips the human-
        // facing pretty output entirely; emits only the structured
        // schema to stdout so developers can pipe to `jq`.
        if ($this->option('as') === 'json') {
            return $this->emitJson($name, $meta);
        }
        if (($rejected = $this->rejectUnknownAsFormat()) !== null) {
            return $rejected;
        }

        // Validate-against mode. Reads the developer's
        // Blade file, finds every <x-wirekit::{name} ...> tag, checks
        // each attribute against the prop list. Emits warnings for
        // unknown attributes + the suggested closest prop.
        if ($this->option('validate-against') !== null) {
            return $this->validateAgainst(
                $name,
                (string) $this->option('validate-against'),
                $this->acceptedAttributeNames($name)
            );
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
            'tokens' => ComponentRegistry::tokensOf($name),
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

        return $this->slotsFromBlade($bladePath);
    }

    /**
     * The slot schema of one Blade file.
     *
     * Split out of `extractSlots()` so the SUB-component path can reach it. That path already
     * knows its own file — what it lacked was any way to ask this question, because
     * `extractSlots()` resolves a top-level name and a sub-component has none: `card.body`
     * resolves to neither `card.body.blade.php` nor `card.body/index.blade.php`, so it
     * returned the empty list for all 87 of them. 68 have a slot contract.
     *
     * @return list<array{name: string, required: bool}>
     */
    private function slotsFromBlade(string $bladePath): array
    {
        return BladeParser::extractSlotsWithMetadataFromSource(
            (string) file_get_contents($bladePath),
            $bladePath,
        );
    }

    /**
     * Every attribute name the component answers to — `@props` UNION `@aware`.
     *
     * The two are deliberately separate everywhere else: `ComponentRegistry::extractProps()`
     * means "the props this component declares", and an `@aware` key is a value the PARENT
     * owns. Folding them together would widen the JSON manifest, the API map and the docs
     * pipeline with names those surfaces do not mean.
     *
     * The unknown-attribute question is the one place they belong together, because Blade
     * accepts either spelling on the tag — and this package answers that question TWICE.
     * `StrictnessGate` (the runtime warning) unions them; `--validate-against` did not. So
     * `<x-wirekit::input announce-errors="false">` rendered clean at runtime and was reported
     * as an unknown attribute by the pre-commit linter, on a name 37 components accept and
     * this library's own form documentation teaches.
     *
     * @return list<string>
     */
    private function acceptedAttributeNames(string $name): array
    {
        return array_map(
            static fn (array $p): string => $p['name'],
            [...ComponentRegistry::extractProps($name), ...ComponentRegistry::extractAwareProps($name)]
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
     *
     * The accepted names arrive as a PARAMETER rather than being looked up here, because the
     * two callers resolve them from different places: a top-level component reads its
     * `@props` and `@aware` through the registry, a sub-component reads them straight off the
     * nested Blade file the registry does not track.
     *
     * @param  list<string>  $knownProps
     */
    private function validateAgainst(string $name, string $developerBladePath, array $knownProps): int
    {
        if (! file_exists($developerBladePath)) {
            $this->error("Developer Blade file not found: {$developerBladePath}");

            return self::FAILURE;
        }

        $content = (string) file_get_contents($developerBladePath);

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
                // The spelling the developer WROTE, minus the binding punctuation.
                // Reported back as-is: a warning that names a string absent from their
                // file reads as a bug in the linter.
                $written = ltrim($attr, ':@');

                // Laravel camelCases every component attribute before it ever reaches
                // the component — `ComponentTagCompiler` does `[Str::camel($key) => $value]`
                // — so `aside-width` IS `$asideWidth` and the two spellings render
                // identically. Comparing the written form against the declared prop names
                // reported the kebab-case spelling as unknown, and kebab-case is what this
                // library's own documentation teaches on nearly every page. The flag is
                // sold for pre-commit hooks, so that fired a red commit on code copied out
                // of the docs.
                $candidate = Str::camel($written);

                if (in_array($candidate, $knownProps, true)) {
                    continue;
                }

                // Allowlist common Blade / Alpine / Livewire attributes
                // that aren't WireKit props but are valid usage.
                //
                // ⚠️ Matched against the COLON-stripped spelling, and camelCasing FIRST
                // would be the mirror-image bug: `x-on:click` camelCases to `xOn:click`,
                // which stops matching `^x-`, so a fix aimed at `:class` would break the
                // passthrough family this already got right. Only the `:` comes off — the
                // `@` must stay, because `^@` is what carries Alpine's shorthand `@click`.
                if (preg_match('/^(class|style|id|slot|wire(:|$)|x-|data-|aria-|@|role|tabindex)/', ltrim($attr, ':'))) {
                    continue;
                }

                $issues[] = [
                    'line' => $line,
                    'attr' => $written,
                    // Suggested from the normalized form, so a kebab-case typo is measured
                    // against the prop names in the spelling those are declared in.
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
     * Refuse an `--as` value that is not a format we emit.
     *
     * Shared because it was not, and the two paths drifted: the top-level branch
     * rejected an unknown format and exited 1, and the dotted sub-component branch
     * — which returns from `handle()` before ever reaching it — silently ignored
     * the flag and exited 0. `wirekit:show card --as=bogus` failed; the same typo
     * on `card.header` printed the human table and reported success, so a script
     * branching on the exit code read a typo as a clean run.
     *
     * Returns null when there is nothing to reject, so a caller reads as
     * "if this answered, return its answer".
     */
    private function rejectUnknownAsFormat(): ?int
    {
        $as = $this->option('as');

        if ($as === null || $as === '' || $as === 'json') {
            return null;
        }

        $this->error("Unknown --as format: {$as}");
        $this->line('  Available: json (default = human-readable table)');

        /*
         * `wirekit:list-fonts`, `wirekit:list-components` and `wirekit:list-icons` all
         * suggest on THIS flag name. A developer who has seen it work there reads the
         * bare list here as "too far off to match" rather than as a missing feature.
         *
         * The accepted set is one entry, and that is still worth a hint: `--as=jsn`
         * scores a distance of 1 and gets answered instead of enumerated.
         */
        $hint = SuggestSimilar::format(
            SuggestSimilar::byLevenshtein((string) $as, ['json'])
        );
        if ($hint !== null) {
            $this->line('  '.$hint);
        }

        return self::FAILURE;
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
        $slots = $this->slotsFromBlade($bladePath);

        /*
         * The same two flags the top-level path resolves, resolved the same way. Both were
         * dropped here: `handleSubComponent()` returns from `handle()` before either is
         * consulted, so `wirekit:show card.body --validate-against=page.blade.php` printed the
         * sub-component's props and exited 0 without opening the file it was handed.
         */
        if ($this->option('validate-against') !== null && (string) $this->option('as') !== '') {
            $this->error('--as and --validate-against cannot be combined.');
            $this->line('  --as prints the component schema; --validate-against lints a Blade file and exits 1 on a finding.');
            $this->line('  Run them as two commands.');

            return self::FAILURE;
        }

        if ($this->option('validate-against') !== null) {
            return $this->validateAgainst(
                $name,
                (string) $this->option('validate-against'),
                array_map(
                    static fn (array $p): string => $p['name'],
                    [...$props, ...PropsParser::parseAwareBlade($bladePath)]
                )
            );
        }

        if (($rejected = $this->rejectUnknownAsFormat()) !== null) {
            return $rejected;
        }

        if ($this->option('as') === 'json') {
            $payload = [
                'name' => $name,
                'parent' => $parent,
                'child' => $child,
                'tag' => "<x-wirekit::{$name}>",
                'props' => $props,
                'slots' => $slots,
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

        /*
         * The line above has always been able to say "slot-only" — and the slots themselves
         * were never printed, in either output. Naming the concept and omitting the data is
         * the worst of the two: a developer reads "slot-only" as a complete answer.
         */
        if ($slots !== []) {
            $this->line('');
            $this->line('  <fg=yellow>Slots:</>');
            foreach ($slots as $slot) {
                $req = $slot['required'] ? '<fg=red>(required)</>' : '<fg=gray>(optional)</>';
                $this->line("    <fg=green>{$slot['name']}</> {$req}");
            }
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
