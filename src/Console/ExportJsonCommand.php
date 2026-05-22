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
            // ComponentRegistry::extractProps() is THE single source of
            // truth for prop extraction. It routes anonymous components
            // through PropsParser (reads @props([...])) and class-based
            // components (chart) through ClassPropsExtractor (Reflection
            // on the constructor signature). Both paths return the same
            // shape so this caller doesn't branch.
            $props = ComponentRegistry::extractProps($name);
            $slots = $bladePath !== null ? $this->extractSlots($bladePath) : [];
            $subComponents = $this->discoverSubComponents($name);

            // docs_url resolves to a publicly visitable page on
            // docs.wirekit.app. When the component's dedicated docs page
            // has no publicly-rendered surface, the field is null so AI
            // tooling clients don't fetch a URL that returns nothing
            // useful. The component itself remains in the manifest (it's
            // still a callable Blade tag); only the docs URL drops.
            $docsUrl = $this->hasPublicDocsPage($name)
                ? "https://docs.wirekit.app/components/{$name}"
                : null;

            $components[] = [
                'name' => $name,
                'tag' => ComponentRegistry::tag($name),
                'category' => $meta['category'],
                'description' => $meta['description'],
                'docs_url' => $docsUrl,
                'props' => $props,
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
     * Whether the component has a publicly-rendered docs page. Reads
     * `docs/components/{name}.md`'s YAML frontmatter; only `visibility:
     * guest` (or no `visibility:` field at all — defaults to guest) counts
     * as public. Missing docs file → false. Used to gate `docs_url`
     * emission so the manifest doesn't advertise URLs that resolve to
     * pages developers can't actually visit.
     */
    private function hasPublicDocsPage(string $name): bool
    {
        $path = dirname(__DIR__, 2)."/docs/components/{$name}.md";
        if (! file_exists($path)) {
            return false;
        }

        $content = (string) file_get_contents($path);

        // Frontmatter must be at the very top, between two `---` lines.
        if (! str_starts_with($content, '---')) {
            // No frontmatter → assume public (consistent with the
            // downstream Markdown parser, which treats missing
            // visibility: as guest by default).
            return true;
        }

        $closing = strpos($content, "\n---", 3);
        if ($closing === false) {
            return true;
        }

        $frontmatter = substr($content, 3, $closing - 3);

        // Match `visibility:` value on its own line. Strict shape avoids
        // false-positives on prose mentions of the literal field name
        // — but we already strip body content above so this is belt-and-
        // braces.
        if (preg_match('/^\s*visibility\s*:\s*([a-z]+)\s*$/mi', $frontmatter, $m)) {
            return strtolower($m[1]) === 'guest';
        }

        return true;
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
    private function extractSlots(string $bladePath): array
    {
        $contents = (string) file_get_contents($bladePath);

        return BladeParser::extractSlotsWithMetadataFromSource($contents, $bladePath);
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
    private function discoverSubComponents(string $name): array
    {
        $subDir = __DIR__.'/../../resources/views/components/'.$name;
        if (! is_dir($subDir)) {
            return [];
        }

        $subFiles = glob($subDir.'/*.blade.php') ?: [];
        $subs = [];
        foreach ($subFiles as $file) {
            $subName = basename($file, '.blade.php');
            if ($subName === 'index') {
                continue;
            }
            $subs[] = $name.'.'.$subName;
        }
        sort($subs);

        return $subs;
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
