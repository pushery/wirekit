<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Pushery\WireKit\ComponentRegistry;
use Pushery\WireKit\Support\SuggestSimilar;

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
        $this->line("  <fg=yellow>Tag:</>         <x-wirekit::{$name}>");
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

        $this->line("  <fg=yellow>Docs:</> docs/components/{$name}.md");

        return self::SUCCESS;
    }

    /**
     * Emit the structured schema for a component as JSON (Ext #2).
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

        // Discover sub-components from the file system (same heuristic
        // as the human-readable path below).
        $subComponents = [];
        $subDir = __DIR__.'/../../resources/views/components/'.$name;
        if (is_dir($subDir)) {
            foreach (glob($subDir.'/*.blade.php') ?: [] as $file) {
                $subName = basename($file, '.blade.php');
                if ($subName === 'index') {
                    continue;
                }
                $subComponents[] = "{$name}.{$subName}";
            }
        }

        $payload = [
            'name' => $name,
            'tag' => "<x-wirekit::{$name}>",
            'category' => $meta['category'],
            'description' => $meta['description'],
            'docs_url' => "https://docs.wirekit.app/components/{$name}",
            'props' => $props,
            'sub_components' => $subComponents,
        ];

        $this->output->write(
            (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)
        );

        return self::SUCCESS;
    }

    /**
     * Validate every `<x-wirekit::{name} attr="..." />` usage in a developer
     * Blade file against the component's known prop set (Ext #3).
     *
     * Heuristic: tag-by-tag attribute scan against the prop names
     * extracted by PropsParser. Unknown attributes that don't match a
     * common Blade-passthrough (`class`, `style`, `id`, `wire:*`,
     * `x-*`, `@*`, `data-*`, `aria-*`) trigger a warning with the
     * closest matching prop name (Levenshtein-ranked).
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

        // Match every <x-wirekit::name ...> opening tag in the developer
        // file. The closing /> or > and the attribute list inside.
        $tagPattern = '/<x-wirekit::'.preg_quote($name, '/').'(\s+[^>]*?)?\s*\/?>/s';
        if (! preg_match_all($tagPattern, $content, $tagMatches, PREG_OFFSET_CAPTURE)) {
            $this->info("No <x-wirekit::{$name}> usages found in {$developerBladePath}");

            return self::SUCCESS;
        }

        $totalUsages = 0;
        $issues = [];
        foreach ($tagMatches[1] as $i => $attrMatch) {
            $totalUsages++;
            $attrBlock = $attrMatch[0];
            if ($attrBlock === '' || $attrBlock === null) {
                continue;
            }
            $offset = $tagMatches[0][$i][1];
            $line = substr_count(substr($content, 0, $offset), "\n") + 1;

            // Crude attribute extraction — captures `name`, `name="value"`,
            // `:name="expr"`, `@name="expr"`. Sufficient for the
            // unknown-attribute heuristic; doesn't need full HTML
            // parsing.
            if (preg_match_all('/(?<![:.@\w])([:@]?[a-zA-Z][a-zA-Z0-9_:-]*)(?:=["\'][^"\']*["\'])?/', $attrBlock, $attrs)) {
                foreach ($attrs[1] as $attr) {
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

                    $closest = $this->closestProp($candidate, $knownProps);
                    $issues[] = [
                        'line' => $line,
                        'attr' => $candidate,
                        'closest' => $closest,
                    ];
                }
            }
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
}
