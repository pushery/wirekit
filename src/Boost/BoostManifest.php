<?php

declare(strict_types=1);

namespace Pushery\WireKit\Boost;

use Pushery\WireKit\Mcp\McpCatalog;
use Pushery\WireKit\Theming\ThemePresetRegistry;

/**
 * Builds the Laravel Boost skill manifest (`.boost/wirekit.json`).
 *
 * A Boost "skill set" is a typed bundle of prompt fragments + component/prop
 * data that an AI-augmented editor loads when working in a project that depends
 * on WireKit. This manifest is auto-derived ENTIRELY from shipped surfaces — it
 * reuses {@see McpCatalog} (the same PropsParser-backed component + token catalog
 * the MCP server serves) plus {@see ThemePresetRegistry} and the registered
 * `wirekit:*` command signatures — so it can never drift from the installed
 * package, and it leaks nothing that isn't already public (no `docs/`, no
 * network, no internal references).
 *
 * `format-version` lets a future schema revision ship without breaking the
 * manifests developers already published.
 */
final class BoostManifest
{
    public const FORMAT_VERSION = 1;

    public function __construct(private readonly McpCatalog $catalog = new McpCatalog) {}

    /** @return array<string, mixed> */
    public function build(string $version): array
    {
        return [
            'name' => 'wirekit',
            'format-version' => self::FORMAT_VERSION,
            'version' => $version,
            'description' => 'WireKit — Laravel Livewire UI component library',
            'skills' => [
                $this->componentSkill(),
                $this->themeSkill(),
                $this->customizationSkill(),
                $this->cliSkill(),
            ],
        ];
    }

    /**
     * The component's sub-components, each with its own props.
     *
     * A Boost manifest that lists `card` but not `card.body` teaches an editor to
     * autocomplete content straight into the card — which has no padding of its
     * own, and is the mistake the shipped AGENTS.md spends a paragraph
     * preventing. Nested under the parent rather than listed flat, so the
     * manifest's component count keeps meaning what it says (WIRE-237).
     *
     * @param  array<string, mixed>  $component
     * @return array<string, mixed>
     */
    private function parts(array $component): array
    {
        $subs = [];

        foreach ($component['sub_components'] ?? [] as $name) {
            $detail = $this->catalog->getComponent($name);

            if ($detail === null) {
                continue;
            }

            $subs[] = [
                'name' => $name,
                'tag' => $detail['tag'],
                'props' => array_map(
                    static fn (array $p): array => [
                        'name' => $p['name'],
                        'default' => $p['default'],
                    ] + ($p['comment'] !== null ? ['hint' => $p['comment']] : []),
                    $detail['props']
                ),
            ];
        }

        return $subs === [] ? [] : ['sub_components' => $subs];
    }

    /** @return array<string, mixed> */
    private function componentSkill(): array
    {
        $components = [];
        foreach ($this->catalog->components() as $c) {
            $detail = $this->catalog->getComponent($c['name']);
            $props = [];
            foreach ($detail['props'] ?? [] as $p) {
                $props[] = [
                    'name' => $p['name'],
                    'default' => $p['default'],
                ] + ($p['comment'] !== null ? ['hint' => $p['comment']] : []);
            }
            $components[] = [
                'name' => $c['name'],
                // Reuse the catalog's registry-derived tag — the interpolated
                // "<x-wirekit::{name}>" form is wrong for the class-based `chart`
                // (real tag <x-wirekit-chart>) (WIRE-209).
                'tag' => $detail['tag'] ?? "<x-wirekit::{$c['name']}>",
                'category' => $c['category'],
                'description' => $c['description'],
                'props' => $props,
            ] + $this->parts($c);
        }

        return [
            'id' => 'wirekit-component',
            'title' => 'Insert a WireKit component',
            'description' => 'Render WireKit Blade components with valid props instead of hand-rolled markup.',
            'prompt-fragment' => 'When rendering a UI element in a WireKit project, prefer <x-wirekit::*> components over hand-rolled markup and Tailwind utility soup. Color comes from the intent / surface props plus the --*-wk-* design tokens — never hardcode colors or use the dark: prefix. Each component below lists its real props with defaults.',
            'components' => $components,
        ];
    }

    /** @return array<string, mixed> */
    private function themeSkill(): array
    {
        return [
            'id' => 'wirekit-theme-switch',
            'title' => 'Switch the WireKit theme preset',
            'prompt-fragment' => 'WireKit ships built-in theme presets. Switch with `php artisan wirekit:theme <preset>` (injects the preset CSS block) or override the --*-wk-* tokens in app.css. Available presets:',
            'presets' => ThemePresetRegistry::keys(),
        ];
    }

    /** @return array<string, mixed> */
    private function customizationSkill(): array
    {
        return [
            'id' => 'wirekit-customization',
            'title' => 'Customize a WireKit component',
            'decision-tree' => [
                'Level 1 — CSS token override: change a --*-wk-* variable in your app.css :root {} block.',
                'Level 2 — PHP defaults: set a component default in config/wirekit.php.',
                'Level 3 — Scoped personalization: the scope prop + WireKit::scope() for per-instance class overrides.',
                'Level 4 — Publish the Blade view: vendor:publish --tag=wirekit-views, then edit the markup.',
            ],
            'instructions' => 'Prefer the lowest level that solves the problem; reach for Level 4 only when the markup itself must change.',
        ];
    }

    /** @return array<string, mixed> */
    private function cliSkill(): array
    {
        return [
            'id' => 'wirekit-cli',
            'title' => 'WireKit CLI commands',
            'instructions' => 'Discover + verify WireKit from the CLI: wirekit:list and wirekit:show <component> reveal the catalog and prop signatures, wirekit:icons lists icon aliases, wirekit:doctor verifies the install, and wirekit:mcp-serve runs the MCP server for live editor context.',
            'commands' => $this->commands(),
        ];
    }

    /** @return list<array{name: string, description: string}> */
    private function commands(): array
    {
        $found = [];
        foreach (glob(\dirname(__DIR__, 2).'/src/Console/*.php') ?: [] as $file) {
            $src = (string) file_get_contents($file);
            if (preg_match('/protected \$signature\s*=\s*[\'"](wirekit:[a-z:-]+)/', $src, $sig)
                && preg_match('/protected \$description\s*=\s*[\'"]([^\'"]+)/', $src, $desc)) {
                $found[$sig[1]] = $desc[1];
            }
        }
        ksort($found);

        return array_map(
            static fn (string $name, string $description): array => ['name' => $name, 'description' => $description],
            array_keys($found),
            array_values($found),
        );
    }
}
