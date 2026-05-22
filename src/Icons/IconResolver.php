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

        // 3. Surface every alias from every active preset for a useful error
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
