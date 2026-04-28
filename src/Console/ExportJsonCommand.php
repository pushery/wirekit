<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Pushery\WireKit\ComponentRegistry;

/**
 * components.json export.
 *
 * Emits a machine-readable manifest of every WireKit component, including
 * category, description, props (parsed from @props([...]) blocks in the
 * Blade file), and slot names (parsed from $slot / @isset($slotName)
 * references). Designed to be consumed by the docs-app's
 * /components.json endpoint, AI tooling, and design-system audits.
 *
 * The docs-app's wrapper (BuildComponentsJsonCommand) calls us twice:
 * once with --pretty, once without. We support both by accepting --pretty
 * and pretty-printing whenever it's set; without the flag we emit
 * minified JSON. Either way: stdout-only, exit 0 on success, JSON
 * decodable.
 *
 * Usage:
 *   php artisan wirekit:export-json --pretty
 *   php artisan wirekit:export-json
 *
 * Output: full JSON document on stdout. Exit code 0 = success.
 */
class ExportJsonCommand extends Command
{
    protected $signature = 'wirekit:export-json {--pretty : Pretty-print the JSON output}';

    protected $description = 'Emit machine-readable JSON manifest of every WireKit component (props + slots + category)';

    public function handle(): int
    {
        $components = [];

        foreach (ComponentRegistry::all() as $name => $meta) {
            $bladePath = $this->resolveBladePath($name);
            $props = $bladePath !== null ? $this->extractProps($bladePath) : [];
            $slots = $bladePath !== null ? $this->extractSlots($bladePath) : [];

            $components[] = [
                'name' => $name,
                'tag' => "<x-wirekit::{$name}>",
                'category' => $meta['category'],
                'description' => $meta['description'],
                'docs_url' => "https://docs.wirekit.app/components/{$name}",
                'props' => $props,
                'slots' => $slots,
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

        // Write to stdout — the docs-app captures whatever we emit and
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
     * Parse @props([...]) block from a Blade file.
     * Returns a list of {name, default} objects. Default values are
     * captured as raw PHP-source strings (not evaluated) so the consumer
     * can render them faithfully.
     *
     * @return list<array{name: string, default: string|null}>
     */
    private function extractProps(string $bladePath): array
    {
        $contents = file_get_contents($bladePath);

        if (! preg_match('/@props\s*\(\s*\[(.*?)\]\s*\)/s', $contents, $match)) {
            return [];
        }

        $body = $match[1];

        // Each line is roughly:  'name' => default,
        // The default may be a complex expression — capture the raw source up
        // to the trailing comma at the line's logical end. We split on
        // top-level commas (not commas inside () or []).
        $entries = $this->splitTopLevel($body);

        $props = [];
        foreach ($entries as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }
            if (! preg_match('/^[\'"]([a-zA-Z_][a-zA-Z0-9_-]*)[\'"]\s*(?:=>\s*(.*))?$/s', $entry, $m)) {
                continue;
            }
            $props[] = [
                'name' => $m[1],
                'default' => isset($m[2]) ? trim($m[2]) : null,
            ];
        }

        return $props;
    }

    /**
     * Split a string on top-level commas (not inside parens/brackets).
     *
     * @return list<string>
     */
    private function splitTopLevel(string $input): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $len = strlen($input);
        for ($i = 0; $i < $len; $i++) {
            $ch = $input[$i];
            if ($ch === '(' || $ch === '[' || $ch === '{') {
                $depth++;
            } elseif ($ch === ')' || $ch === ']' || $ch === '}') {
                $depth--;
            }
            if ($ch === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';

                continue;
            }
            $current .= $ch;
        }
        if (trim($current) !== '') {
            $parts[] = $current;
        }

        return $parts;
    }

    /**
     * Parse named-slot references from a Blade file.
     *
     * Detection strategy: slots are reliably identified by `@isset($name)`
     * checks — that's the canonical "is this slot supplied?" pattern.
     * Bare `$slot` (the default slot) is always included if the file
     * references it. Bare `{{ $name }}` is too noisy to use as a signal
     * (catches every prop interpolation and Blade local), so we ignore it
     * for slot detection.
     *
     * Filtering: known prop names from the same component's @props block
     * are removed, and Blade-reserved names (loop, attributes, errors,
     * slot) are excluded from the @isset capture but `slot` is added back
     * if the file uses {{ $slot }}.
     *
     * @return list<string>
     */
    private function extractSlots(string $bladePath): array
    {
        $contents = file_get_contents($bladePath);
        $slots = [];

        // Primary signal: isset($name) blocks identify slot-presence checks.
        // Matches both @isset(...) Blade directive AND isset(...) inside
        // @if / @elseif clauses (e.g. stat uses @elseif(isset($iconSlot))).
        if (preg_match_all('/\bisset\s*\(\s*\$([a-zA-Z][a-zA-Z0-9]*)\s*\)/', $contents, $matches)) {
            foreach ($matches[1] as $name) {
                $slots[$name] = true;
            }
        }

        // Default slot: include 'slot' if the file outputs {{ $slot }} or
        // checks $slot->isNotEmpty(). Some components use the default slot
        // without an isset check (it's always defined).
        if (preg_match('/\$slot\b/', $contents)) {
            $slots['slot'] = true;
        }

        // Drop prop names and Blade-reserved names.
        $propNames = array_column($this->extractProps($bladePath), 'name');
        $reserved = ['loop', 'attributes', 'errors'];
        $slotNames = array_diff(array_keys($slots), $propNames, $reserved);

        return array_values(array_unique($slotNames));
    }

    private function packageVersion(): string
    {
        $composerPath = __DIR__.'/../../composer.json';
        if (! file_exists($composerPath)) {
            return 'unknown';
        }
        $composer = json_decode(file_get_contents($composerPath), true);

        return $composer['version'] ?? 'dev';
    }
}
