<?php

declare(strict_types=1);

namespace Pushery\WireKit\Icons;

use InvalidArgumentException;
use Pushery\WireKit\Contracts\IconPreset;
use Pushery\WireKit\Icons\Presets\HeroiconsAppPreset;
use Pushery\WireKit\Icons\Presets\HeroiconsMarketingPreset;
use Pushery\WireKit\Icons\Presets\HeroiconsPreset;
use Pushery\WireKit\Icons\Presets\LucidePreset;
use Pushery\WireKit\Icons\Presets\PhosphorPreset;
use Pushery\WireKit\Icons\Presets\TablerPreset;
use Pushery\WireKit\Support\SuggestSimilar;

final class IconResolver
{
    /**
     * In-memory cache for resolved icons within a single request.
     *
     * @var array<string, string>
     */
    private array $resolved = [];

    /**
     * Mapping of preset keys to preset classes.
     *
     * @var array<string, class-string<IconPreset>>
     */
    private const BUILT_IN_PRESETS = [
        'heroicons' => HeroiconsPreset::class,
        'heroicons-app' => HeroiconsAppPreset::class,
        'heroicons-marketing' => HeroiconsMarketingPreset::class,
        'lucide' => LucidePreset::class,
        'phosphor' => PhosphorPreset::class,
        'tabler' => TablerPreset::class,
    ];

    /**
     * Cached active presets, in resolution order (later wins).
     *
     * @var list<IconPreset>|null
     */
    private ?array $presets = null;

    /**
     * Resolve a semantic alias to the actual Blade Icon identifier.
     *
     * Results are cached — subsequent calls with the same alias
     * within the same request are a pure array hit (~0.001ms).
     */
    public function resolve(string $alias): string
    {
        return $this->resolved[$alias] ??= $this->lookup($alias);
    }

    /**
     * Validate that the configured preset(s) exist (without resolving any icons).
     * Throws InvalidArgumentException if a preset key cannot be loaded.
     */
    public function validatePreset(): void
    {
        $this->getPresets();
    }

    /**
     * Get the list of available built-in preset keys.
     *
     * @return array<string>
     */
    public static function availablePresets(): array
    {
        return array_keys(self::BUILT_IN_PRESETS);
    }

    /**
     * Internal lookup: developer aliases -> preset chain (later wins) -> exception.
     */
    private function lookup(string $alias): string
    {
        // 1. Developer-level alias overrides always win
        $aliases = config('wirekit.icons.aliases', []);
        if (isset($aliases[$alias])) {
            return $aliases[$alias];
        }

        // 2. Walk presets right-to-left so later (extension) presets override earlier ones
        $presets = $this->getPresets();
        for ($i = count($presets) - 1; $i >= 0; $i--) {
            $icons = $presets[$i]->icons();
            if (isset($icons[$alias])) {
                return $icons[$alias];
            }
        }

        // 3. fallthrough to the
        // underlying blade-icons identifier when the alias matches the
        // ICON name (no prefix) in the active preset family. This
        // catches the bug class where developers write
        // `<x-wirekit::icon name="briefcase" />` expecting the
        // blade-heroicons icon — pre-fix we threw because `briefcase`
        // wasn't aliased, even though the underlying SVG ships in the
        // package. We log an INFO line so the dev knows the alias
        // fell through (and consider promoting it to the preset).
        $fallthrough = $this->resolveFallthrough($alias, $presets);
        if ($fallthrough !== null) {
            // Log at info level only — visible in development logs as
            // a hint to add the alias to the preset, but invisible
            // in production noise.
            if (function_exists('logger')) {
                logger()->info(
                    "WireKit: Icon alias '{$alias}' resolved via fallthrough to '{$fallthrough}'. ".
                    'Consider adding it to your active icon preset.'
                );
            }

            return $fallthrough;
        }

        // 4. Surface every alias from every active preset for a useful error
        $available = [];
        foreach ($presets as $preset) {
            $available = array_merge($available, array_keys($preset->icons()));
        }
        $available = array_values(array_unique($available));
        sort($available);

        // Cross-cutting Did-you-mean — same Levenshtein helper as
        // WireKit::validateProp() and every wirekit:* CLI surface.
        // Aliases routinely collide on close typos (`bolt` vs `bell`,
        // `close` vs `clock`, `chevron-down` vs `chevron-up`), so a
        // ranked suggestion turns the exception into an actionable hint.
        $message = "WireKit: Unknown icon alias '{$alias}'. ";
        $hint = SuggestSimilar::format(
            SuggestSimilar::byLevenshtein($alias, $available)
        );
        if ($hint !== null) {
            $message .= $hint.' ';
        }
        $message .= 'Available aliases: '.implode(', ', $available);

        throw new InvalidArgumentException($message);
    }

    /**
     * Fallthrough resolution: try `{prefix}-{alias}` and the two
     * heroicons style variants for the icon name as the developer
     * typed it. Returns the first match or null if none exists.
     *
     * Per-family prefix mapping:
     *   - heroicons     → `heroicon-m-{alias}`, `heroicon-o-{alias}`, `heroicon-s-{alias}`
     *   - lucide        → `lucide-{alias}`
     *   - phosphor      → `phosphor-{alias}`
     *   - tabler        → `tabler-{alias}`
     *
     * @param  list<IconPreset>  $presets
     */
    private function resolveFallthrough(string $alias, array $presets): ?string
    {
        // blade-ui-kit/blade-icons binds its Factory under the FQCN
        // `BladeUI\Icons\Factory::class` — NOT the dotted `'blade.icons'`
        // string the earlier shape of this method probed. The dotted
        // form has never been a registered alias in blade-icons; this
        // method silently returned null on every call until a developer
        // ran into a missing-alias and the throw branch fired.
        //
        // We probe the FQCN first (the real binding), and fall back to
        // the legacy dotted name for forward-compat if blade-icons ever
        // ships an alias by that name.
        $factoryClass = 'BladeUI\\Icons\\Factory';
        $svgFactory = null;
        if (function_exists('app')) {
            $container = app();
            if ($container->bound($factoryClass)) {
                $svgFactory = $container->make($factoryClass);
            } elseif ($container->bound('blade.icons')) {
                $svgFactory = $container->make('blade.icons');
            }
        }

        if ($svgFactory === null) {
            return null;
        }

        // Determine which icon family is active by introspecting any
        // preset's first alias — the prefix is the same for the whole
        // family.
        foreach ($presets as $preset) {
            $icons = $preset->icons();
            if ($icons === []) {
                continue;
            }
            $firstValue = reset($icons);
            $prefix = $this->familyPrefix((string) $firstValue);

            if ($prefix === null) {
                continue;
            }

            // For heroicons, try mini / outline / solid in that order
            // (mini is the canonical UI size).
            $candidates = $prefix === 'heroicon-m'
                ? ["heroicon-m-{$alias}", "heroicon-o-{$alias}", "heroicon-s-{$alias}"]
                : ["{$prefix}-{$alias}"];

            foreach ($candidates as $candidate) {
                try {
                    if (method_exists($svgFactory, 'svg')) {
                        // Successful lookup returns an Htmlable; failure throws.
                        $svgFactory->svg($candidate);

                        return $candidate;
                    }
                } catch (\Throwable) {
                    // Try next candidate.
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * Extract the family-prefix (everything up to the LAST `-`) from
     * a full blade-icon identifier like `heroicon-m-x-mark` →
     * `heroicon-m`. The blade-icons package uses `{set}-{name}` for
     * Lucide / Phosphor / Tabler and `{set}-{style}-{name}` for
     * heroicons (where style is m / o / s).
     */
    private function familyPrefix(string $fullIdentifier): ?string
    {
        // Heroicons: `heroicon-m-...` / `heroicon-o-...` / `heroicon-s-...`
        if (preg_match('/^(heroicon-[mos])-/', $fullIdentifier, $m) === 1) {
            return $m[1];
        }

        // Other families: `lucide-...` / `phosphor-...` / `tabler-...`
        if (preg_match('/^([a-z]+)-/', $fullIdentifier, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /**
     * Resolve and cache the active preset chain.
     *
     * Config priority (highest to lowest):
     *   1. wirekit.icons.presets — array of preset keys / class names
     *   2. wirekit.icons.preset  — single preset key / class name (back-compat)
     *   3. fallback: ['heroicons']
     *
     * @return list<IconPreset>
     */
    private function getPresets(): array
    {
        if ($this->presets !== null) {
            return $this->presets;
        }

        // New plural form takes precedence when explicitly configured
        $multi = config('wirekit.icons.presets');
        if (is_array($multi) && $multi !== []) {
            $this->presets = array_values(array_map(
                fn ($entry) => $this->instantiatePreset($entry),
                $multi
            ));

            return $this->presets;
        }

        // Back-compat: single string preset, normalized into a one-element array
        $single = config('wirekit.icons.preset', 'heroicons');
        $this->presets = [$this->instantiatePreset($single)];

        return $this->presets;
    }

    /**
     * Resolve a preset key or class name to an IconPreset instance.
     */
    private function instantiatePreset(mixed $entry): IconPreset
    {
        if (is_string($entry) && isset(self::BUILT_IN_PRESETS[$entry])) {
            $class = self::BUILT_IN_PRESETS[$entry];

            return new $class;
        }

        if (is_string($entry) && class_exists($entry)) {
            $instance = new $entry;

            if (! $instance instanceof IconPreset) {
                throw new InvalidArgumentException(
                    "WireKit: Custom icon preset '{$entry}' must implement "
                    .IconPreset::class
                );
            }

            return $instance;
        }

        $value = is_string($entry) ? $entry : get_debug_type($entry);

        $available = array_keys(self::BUILT_IN_PRESETS);
        $message = "WireKit: Unknown icon preset '{$value}'. ";
        $hint = SuggestSimilar::format(
            SuggestSimilar::byLevenshtein($value, $available)
        );
        if ($hint !== null) {
            $message .= $hint.' ';
        }
        $message .= 'Available: '.implode(', ', $available)
            .' or a fully qualified class name implementing '.IconPreset::class;

        throw new InvalidArgumentException($message);
    }
}
