<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Pushery\WireKit\ComponentRegistry;
use Pushery\WireKit\Fonts\FontRegistry;
use Pushery\WireKit\Icons\IconResolver;
use Pushery\WireKit\Support\DocsVisibility;
use Pushery\WireKit\Support\VersionResolver;
use Pushery\WireKit\Theming\ThemePresetRegistry;
use Pushery\WireKit\WireKit;

/**
 * emit a hierarchical AI-friendly site sitemap covering
 * components, tokens, fonts, icon presets, layouts, blueprints, and CLI
 * commands. Superset of `/components.json` ( F2) — designed for
 * MCP servers, Claude Code, ChatGPT Codex, Cursor, Aider, and other AI
 * tooling that needs a single entry point to enumerate every WireKit surface.
 *
 * Output shape:
 *   {
 *     version: "1.x.x",
 *     generated_at: ISO-8601,
 *     docs_base: "https://docs.wirekit.app",
 *     groups: [
 *       { id: "components", count, items: [...] },
 *       { id: "themes", count, items: [...] },
 *       { id: "fonts", count, items: [...] },
 *       { id: "icons", count, items: [...] },
 *       { id: "layouts", count, items: [...] },
 *       { id: "blueprints", count, items: [...] },
 *       { id: "recipes", count, items: [...] },
 *       { id: "commands", count, items: [...] }
 *     ]
 *   }
 *
 * Output is XSS-safe: `JSON_HEX_TAG` is set so user-controlled string
 * values containing `</script>` cannot break out of a consuming
 * `<script type="application/ld+json">` block.
 *
 * --public restricts every documentation-backed group to entries whose
 * docs page is publicly rendered — the variant docs.wirekit.app serves
 * at /api-map.json:
 *
 *   - components: a component whose dedicated docs page is not publicly
 *     rendered is omitted (page-less sub-components documented on a
 *     parent page stay — a missing page is not the same as a non-public
 *     one).
 *   - the page groups: keep only pages that are publicly rendered.
 *   - css-classes: omit a marker class whose component's docs page is
 *     not publicly rendered.
 *
 * Code-only groups (themes, fonts, icons, commands, helpers) describe
 * shipped public API and are never filtered.
 */
class ExportApiMapCommand extends Command
{
    protected $signature = 'wirekit:export-api-map
        {--pretty : Pretty-print (multi-line) output}
        {--public : Emit the sitemap docs.wirekit.app serves at its /api-map.json endpoint (the default emits the full sitemap).}';

    protected $description = 'Emit a machine-readable AI-friendly sitemap of every WireKit surface';

    public function handle(): int
    {
        $packageRoot = realpath(__DIR__.'/../..');
        if ($packageRoot === false) {
            $this->error('Could not resolve package root.');

            return self::FAILURE;
        }

        $payload = [
            'version' => $this->detectVersion($packageRoot),
            'generated_at' => date('c'),
            'docs_base' => WireKit::DOCS_URL,
            'groups' => [
                $this->componentsGroup(),
                $this->themesGroup(),
                $this->fontsGroup(),
                $this->iconsGroup(),
                $this->layoutsGroup($packageRoot),
                $this->blueprintsGroup($packageRoot),
                $this->recipesGroup($packageRoot),
                $this->commandsGroup(),
                $this->helpersGroup(),
                $this->cssClassesGroup($packageRoot),
            ],
        ];

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG;
        if ($this->option('pretty')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $this->line(json_encode($payload, $flags));

        return self::SUCCESS;
    }

    /**
     * Resolve the running WireKit version. Delegates to VersionResolver so
     * the three export commands stay in lockstep — see VersionResolver for
     * the priority order. `$packageRoot` is preserved for backward-compat
     * with the existing call site but no longer consulted directly.
     */
    private function detectVersion(string $packageRoot): string
    {
        unset($packageRoot);

        return VersionResolver::resolve();
    }

    /**
     * @return array{id: string, count: int, items: array<int, array<string, mixed>>}
     */
    private function componentsGroup(): array
    {
        $items = [];
        foreach (ComponentRegistry::all() as $name => $meta) {
            $pageStatus = DocsVisibility::componentPageStatus($name);

            // --public: a component whose page is not publicly rendered
            // is omitted ENTIRELY — same contract as ExportJsonCommand.
            // MISSING is kept (page-less sub-component on a parent page).
            if ($this->option('public') && $pageStatus === DocsVisibility::STATUS_STAGED) {
                continue;
            }

            // Transform the canonical open-tag form (`<x-wirekit::name>`
            // OR class-based override `<x-wirekit-chart>`) into the
            // self-closing form expected by AI / IDE tooling
            // (`<x-wirekit::name />`, `<x-wirekit-chart />`). One
            // source of truth in ComponentRegistry::tag().
            $openTag = ComponentRegistry::tag($name);
            $selfClosingTag = substr($openTag, 0, -1).' />';

            $items[] = [
                'id' => $name,
                'tag' => $selfClosingTag,
                'category' => $meta['category'] ?? 'Other',
                'description' => $meta['description'] ?? '',
                // docs_url drops to null when the docs page isn't publicly
                // visitable — same contract as ExportJsonCommand. AI tooling
                // consuming the api-map then knows the component exists
                // (it's still a callable tag) but won't fetch a URL that
                // resolves to no useful content.
                'docs_url' => $pageStatus === DocsVisibility::STATUS_PUBLIC
                    ? WireKit::DOCS_URL.'/components/'.$name
                    : null,
            ];
        }

        return ['id' => 'components', 'count' => count($items), 'items' => $items];
    }

    /**
     * @return array{id: string, count: int, items: array<int, array<string, string>>}
     */
    private function themesGroup(): array
    {
        $presets = ThemePresetRegistry::keys();
        $items = array_map(fn (string $p): array => [
            'id' => $p,
            'install' => 'php artisan wirekit:theme '.$p,
            'docs_url' => WireKit::DOCS_URL.'/theming#'.$p,
        ], $presets);

        return ['id' => 'themes', 'count' => count($items), 'items' => $items];
    }

    /**
     * @return array{id: string, count: int, items: array<int, array<string, mixed>>}
     */
    private function fontsGroup(): array
    {
        $items = [];
        foreach (FontRegistry::all() as $key => $preset) {
            $items[] = [
                'id' => $key,
                'category' => $preset->category,
                'family' => $preset->family,
                'docs_url' => WireKit::DOCS_URL.'/fonts',
            ];
        }

        return ['id' => 'fonts', 'count' => count($items), 'items' => $items];
    }

    /**
     * @return array{id: string, count: int, items: array<int, array<string, mixed>>}
     */
    private function iconsGroup(): array
    {
        $presets = IconResolver::availablePresets();
        $items = array_map(fn (string $p): array => [
            'id' => $p,
            'install' => 'php artisan wirekit:publish-icons '.$p,
            'docs_url' => WireKit::DOCS_URL.'/icons#'.$p,
        ], $presets);

        return ['id' => 'icons', 'count' => count($items), 'items' => $items];
    }

    /**
     * @return array{id: string, count: int, items: array<int, array<string, string>>}
     */
    private function layoutsGroup(string $packageRoot): array
    {
        return $this->scanDocsDir($packageRoot, 'layouts');
    }

    /**
     * @return array{id: string, count: int, items: array<int, array<string, string>>}
     */
    private function blueprintsGroup(string $packageRoot): array
    {
        return $this->scanDocsDir($packageRoot, 'blueprints');
    }

    /**
     * @return array{id: string, count: int, items: array<int, array<string, string>>}
     */
    private function recipesGroup(string $packageRoot): array
    {
        return $this->scanDocsDir($packageRoot, 'recipes');
    }

    /**
     * @return array{id: string, count: int, items: array<int, array<string, string>>}
     */
    private function scanDocsDir(string $packageRoot, string $subdir): array
    {
        $base = $packageRoot.'/docs/'.$subdir;
        $items = [];

        if (! is_dir($base)) {
            return ['id' => $subdir, 'count' => 0, 'items' => []];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'md') {
                continue;
            }

            // Skip group-index pages (usually `index.md` or empty stubs)
            if ($file->getBasename() === 'index.md') {
                continue;
            }

            // --public: these groups ARE pages — a page that is not
            // publicly rendered (or a draft) is omitted, mirroring the
            // blocks export's public filter. This keeps non-public pages
            // out of the publicly-served /api-map.json.
            if ($this->option('public')
                && DocsVisibility::pageStatus($file->getPathname()) !== DocsVisibility::STATUS_PUBLIC) {
                continue;
            }

            $relativePath = ltrim(str_replace($base, '', $file->getPathname()), '/\\');
            $slug = preg_replace('/\.md$/', '', $relativePath);
            $slug = str_replace(DIRECTORY_SEPARATOR, '/', (string) $slug);

            $title = $this->extractFrontmatterTitle($file->getPathname()) ?? $this->humanise(basename((string) $slug));

            $items[] = [
                'id' => $slug,
                'title' => $title,
                'docs_url' => WireKit::DOCS_URL.'/'.$subdir.'/'.$slug,
            ];
        }

        usort($items, fn ($a, $b) => strcmp($a['id'], $b['id']));

        return ['id' => $subdir, 'count' => count($items), 'items' => $items];
    }

    private function extractFrontmatterTitle(string $path): ?string
    {
        $head = (string) file_get_contents($path, false, null, 0, 1024);
        if (! preg_match('/^---\s*\n(.+?)\n---/s', $head, $m)) {
            return null;
        }
        if (preg_match('/^title:\s*(.+)$/m', $m[1], $tm)) {
            return trim($tm[1], " \t'\"");
        }

        return null;
    }

    private function humanise(string $slug): string
    {
        $human = str_replace(['-', '_', '/'], [' ', ' ', ' › '], $slug);

        return ucwords($human);
    }

    /**
     * @return array{id: string, count: int, items: array<int, array<string, string>>}
     */
    private function commandsGroup(): array
    {
        // Discover commands dynamically from Symfony's Application — the
        // service provider's registered command list IS the source of
        // truth. A hardcoded inventory drifts every time a new command
        // ships (the cross-plan audit caught wirekit:class-by-area +
        // wirekit:glass missing from a stale literal list).
        $application = $this->getApplication();
        $commands = [];
        if ($application !== null) {
            foreach ($application->all() as $name => $command) {
                if (str_starts_with($name, 'wirekit:')) {
                    $commands[] = $name;
                }
            }
        }
        sort($commands);

        // Symfony aliases share the canonical command's docs anchor —
        // wirekit:doctor IS wirekit:verify, so its docs_url points at the
        // verify anchor (not a separate doctor anchor that would 404).
        // The alias map stays static here because Symfony's reflection
        // surface for aliases is per-instance and noisier than worth.
        $aliasOf = [
            'wirekit:doctor' => 'wirekit:verify',
        ];
        $items = array_map(function (string $c) use ($aliasOf): array {
            $canonical = $aliasOf[$c] ?? $c;

            return [
                'id' => $c,
                'docs_url' => WireKit::DOCS_URL.'/cli#'.str_replace(':', '', $canonical),
            ];
        }, $commands);

        return ['id' => 'commands', 'count' => count($items), 'items' => $items];
    }

    /**
     * Alpine helpers exposed by the WireKit JS bundle (full bundle only).
     *
     * Each entry describes a `x-data="…"` magic — its preset / parameter
     * surface, trigger options, and the reduced-motion contract. Useful
     * for MCP servers / AI tooling that wants to suggest the right
     * `<div x-data="...">` shape without grepping the source.
     *
     * @return array{id: string, count: int, items: array<int, array<string, mixed>>}
     */
    private function helpersGroup(): array
    {
        $items = [
            [
                'id' => 'wirekitAnimate',
                'description' => 'Reveal-animation Alpine helper. Adds a wk-animate-{preset} class to its host element when a configured trigger fires.',
                'parameters' => [
                    ['name' => 'preset', 'type' => 'enum', 'required' => true],
                    ['name' => 'options.trigger', 'type' => 'enum', 'default' => 'viewport'],
                    ['name' => 'options.once', 'type' => 'bool', 'default' => true],
                    ['name' => 'options.threshold', 'type' => 'float', 'default' => 0.4],
                    ['name' => 'options.duration', 'type' => 'enum', 'default' => 'normal'],
                ],
                'preset_enum' => [
                    'fade-in', 'fade-out',
                    'slide-up-in', 'slide-up-out',
                    'slide-down-in', 'slide-down-out',
                    'slide-left-in', 'slide-left-out',
                    'slide-right-in', 'slide-right-out',
                    'scale-in', 'scale-out',
                    'zoom-in', 'zoom-out',
                    'flip-in', 'flip-out',
                    'rotate-in', 'rotate-out',
                    'bounce-in', 'bounce-out',
                    'spring-in', 'spring-out',
                ],
                'trigger_enum' => ['viewport', 'click', 'manual'],
                'duration_enum' => ['fast', 'normal', 'slow'],
                'respects_reduced_motion' => true,
                'docs_url' => WireKit::DOCS_URL.'/animations#wirekit-animate',
                'blade_wrapper' => '<x-wirekit::reveal preset="…">',
            ],
            [
                'id' => 'wirekitStatAnimate',
                'description' => 'Counter-animation Alpine helper for `<x-wirekit::stat animate>`. Animates the bound `value` from 0 to a `data-target` over 1.2s ease-out cubic. Exposes `animating` + `progress` reactive state for description-fade-in / description-color-count-up opt-ins.',
                'parameters' => [
                    ['name' => 'data-target', 'type' => 'string (numeric or numeric+suffix)', 'required' => true],
                ],
                'reactive_state' => [
                    ['name' => 'value', 'type' => 'string', 'description' => 'Current eased value, locale-formatted'],
                    ['name' => 'animating', 'type' => 'bool', 'description' => 'true while counter runs'],
                    ['name' => 'progress', 'type' => 'float', 'description' => '0..1 eased progress'],
                ],
                'respects_reduced_motion' => true,
                'docs_url' => WireKit::DOCS_URL.'/components/stat#counter-animation',
                'blade_wrapper' => '<x-wirekit::stat animate>',
            ],
        ];

        return ['id' => 'helpers', 'count' => count($items), 'items' => $items];
    }

    /**
     * Public-CSS-API class catalog — every `wk-*` class WireKit emits
     * either via `dist/wirekit.css` or via Blade-template static
     * strings. Mirrors `docs/extending/public-css-api.md`. The catalog
     * and this emission are anti-drift enforced to stay in lockstep with
     * the actual shipped CSS — adding or removing a `wk-*` class without
     * updating the catalog fails the upstream build.
     *
     * @return array{id: string, count: int, items: array<int, array<string, string>>}
     */
    private function cssClassesGroup(string $packageRoot): array
    {
        $classes = [];

        // (1) Compiled CSS selectors.
        $css = (string) file_get_contents($packageRoot.'/dist/wirekit.css');
        $css = (string) preg_replace('~/\*.*?\*/~s', '', $css);
        preg_match_all('/(?<=^|\s|,)\.(\bwk-[a-z][a-z0-9_-]*(?:__[a-z][a-z0-9_-]*)?(?:--[a-z][a-z0-9_-]*)?)\b/m', $css, $cssMatches);
        foreach ($cssMatches[1] as $class) {
            $classes[$class] = true;
        }

        // (2) Static-string emissions in Blade.
        $bladeDir = $packageRoot.'/resources/views/components';
        if (is_dir($bladeDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($bladeDir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $source = (string) file_get_contents($file->getPathname());
                preg_match_all(
                    '/(?<![-a-z0-9])(\bwk-[a-z][a-z0-9_-]*(?:__[a-z][a-z0-9_-]*)?(?:--[a-z][a-z0-9_-]*)?)(?=[\s\'">,])/',
                    $source,
                    $bladeMatches
                );
                foreach ($bladeMatches[1] as $class) {
                    if (str_ends_with($class, '-')) {
                        continue;
                    }
                    $classes[$class] = true;
                }
            }
        }

        $list = array_keys($classes);
        sort($list);

        // --public: drop the marker class of a component whose docs
        // page is not publicly rendered — a class whose wk-stripped name
        // IS a registry component with a non-public docs page names that
        // component to AI tooling enumerating the list. The public-CSS-API
        // catalog PAGE row is a separate, deliberate surface (kept since
        // v2.2.0, lockstep-guarded) and is NOT affected by this filter.
        // Non-component class names (wk-glass-refract, wk-stagger, …)
        // never match a registry key and always stay.
        if ($this->option('public')) {
            $registry = ComponentRegistry::all();
            $list = array_values(array_filter($list, function (string $class) use ($registry): bool {
                $owner = substr($class, 3); // strip the wk- prefix
                if (! array_key_exists($owner, $registry)) {
                    return true;
                }

                return DocsVisibility::componentPageStatus($owner) !== DocsVisibility::STATUS_STAGED;
            }));
        }

        // Per-class metadata. Tier reflects the catalog in
        // docs/extending/public-css-api.md — wk-animate-* state classes
        // carry the "Internal-with-exception" tier per the catalog's
        // Stability tiers section, every other class is "Stable".
        //
        // docs_url anchors to the SUB-SECTION heading inside the
        // catalog (Animation / Motion, Layout / Chrome Markers, etc.)
        // so AI tooling lands the developer at the relevant table row
        // instead of the page top. The catalog's H3 sub-section
        // anchors derive from the Markdown heading text via standard
        // slugification — `### Animation / motion` → `#animation--motion`.
        $items = array_map(function (string $class): array {
            $isAnimation = preg_match('/^wk-animate-/', $class) === 1;

            return [
                'id' => $class,
                'tier' => $isAnimation ? 'Internal-with-exception' : 'Stable',
                'docs_url' => WireKit::DOCS_URL.'/extending/public-css-api#'.$this->cssClassSection($class),
            ];
        }, $list);

        return ['id' => 'css-classes', 'count' => count($items), 'items' => $items];
    }

    /**
     * Maps a wk-* class to the docs/extending/public-css-api.md sub-section anchor
     * it lives under. Mirrors the catalog's 4-group structure so the
     * AI-tooling docs_url lands at the relevant table.
     */
    private function cssClassSection(string $class): string
    {
        if (preg_match('/^wk-animate-/', $class) || $class === 'wk-stagger' || $class === 'wk-transition') {
            return 'animation--motion';
        }
        if (preg_match('/^wk-reading-/', $class)) {
            return 'reading-family';
        }
        // Display / loading classes (chart-mixed, command-list, glass-refract,
        // progress-*, replay-button, skeleton, slider, sparkline,
        // submenu-indicator).
        $displayClasses = [
            'wk-chart-mixed',
            'wk-command-list',
            'wk-glass-refract',
            'wk-progress-indeterminate',
            'wk-progress-circle-indeterminate',
            'wk-replay-button',
            'wk-skeleton',
            'wk-slider',
            'wk-sparkline',
            'wk-submenu-indicator',
        ];
        if (in_array($class, $displayClasses, true)) {
            return 'display--loading';
        }

        // Default — Layout / chrome markers (brand, brand-bar, cta,
        // footer, header, hero, list, list-spacing-*, main,
        // navbar-mobile, prose, scrollbar, section, spine-aware).
        return 'layout--chrome-markers';
    }
}
