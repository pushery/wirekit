<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Pushery\WireKit\ComponentRegistry;
use Pushery\WireKit\Support\BladeParser;
use Pushery\WireKit\Support\PropsParser;
use Pushery\WireKit\Support\VersionResolver;

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
            // PropsParser is the canonical @props extractor. Routes through
            // the same token-stream parser used by every CLI developer +
            // the drift-audit guard. Replaces the historical inline regex
            // parser that silently truncated config(...) defaults and
            // leaked trailing inline comments.
            $props = $bladePath !== null ? PropsParser::parseBlade($bladePath) : [];
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
     * Parse named-slot references from a Blade file. Delegates to
     * `BladeParser::extractSlots()` which is the canonical
     * Blade-content-as-data surface alongside PropsParser. Kept as a
     * thin wrapper so the surrounding `handle()` reads cleanly.
     *
     * @return list<string>
     */
    private function extractSlots(string $bladePath): array
    {
        return BladeParser::extractSlots($bladePath);
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
