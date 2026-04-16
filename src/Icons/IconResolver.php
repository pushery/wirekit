<?php

declare(strict_types=1);

namespace Pushery\WireKit\Icons;

use InvalidArgumentException;
use Pushery\WireKit\Contracts\IconPreset;
use Pushery\WireKit\Icons\Presets\HeroiconsPreset;
use Pushery\WireKit\Icons\Presets\LucidePreset;
use Pushery\WireKit\Icons\Presets\PhosphorPreset;
use Pushery\WireKit\Icons\Presets\TablerPreset;

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
        'lucide' => LucidePreset::class,
        'phosphor' => PhosphorPreset::class,
        'tabler' => TablerPreset::class,
    ];

    private ?IconPreset $preset = null;

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
     * Validate that the configured preset is valid (without resolving any icons).
     * Throws InvalidArgumentException if the preset does not exist.
     */
    public function validatePreset(): void
    {
        $this->getPreset();
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
     * Internal lookup: config override -> preset mapping -> exception.
     */
    private function lookup(string $alias): string
    {
        // 1. Check user-level alias overrides (individual alias replacements)
        $aliases = config('wirekit.icons.aliases', []);
        $override = $aliases[$alias] ?? null;

        if ($override !== null) {
            return $override;
        }

        // 2. Preset mapping
        $preset = $this->getPreset();
        $icons = $preset->icons();

        if (! isset($icons[$alias])) {
            throw new InvalidArgumentException(
                "WireKit: Unknown icon alias '{$alias}'. "
                .'Available aliases: '.implode(', ', array_keys($icons))
            );
        }

        return $icons[$alias];
    }

    /**
     * Resolve and cache the active preset instance.
     */
    private function getPreset(): IconPreset
    {
        if ($this->preset !== null) {
            return $this->preset;
        }

        $presetConfig = config('wirekit.icons.preset', 'heroicons');

        // String -> built-in preset
        if (is_string($presetConfig) && isset(self::BUILT_IN_PRESETS[$presetConfig])) {
            $class = self::BUILT_IN_PRESETS[$presetConfig];
            $this->preset = new $class;

            return $this->preset;
        }

        // Class-string -> custom preset
        if (is_string($presetConfig) && class_exists($presetConfig)) {
            $instance = new $presetConfig;

            if (! $instance instanceof IconPreset) {
                throw new InvalidArgumentException(
                    "WireKit: Custom icon preset '{$presetConfig}' must implement "
                    .IconPreset::class
                );
            }

            $this->preset = $instance;

            return $this->preset;
        }

        throw new InvalidArgumentException(
            "WireKit: Unknown icon preset '{$presetConfig}'. "
            .'Available: '.implode(', ', array_keys(self::BUILT_IN_PRESETS))
            .' or a fully qualified class name implementing '.IconPreset::class
        );
    }
}
