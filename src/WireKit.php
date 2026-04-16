<?php

declare(strict_types=1);

namespace Pushery\WireKit;

use Closure;
use Pushery\WireKit\Icons\IconResolver;

class WireKit
{
    /** @var array<string, array<string, mixed>> */
    protected static array $defaults = [];

    /** @var array<string, array<string, array<string, mixed>>> */
    protected static array $scoped = [];

    /** @var array<string, Closure|array> */
    protected static array $personalizations = [];

    /**
     * Define global defaults for components.
     *
     * Usage in AppServiceProvider::boot():
     *   WireKit::defaults([
     *       'button' => ['variant' => 'primary', 'size' => 'md'],
     *       'input'  => ['variant' => 'outlined'],
     *   ]);
     */
    public static function defaults(array|Closure $defaults): void
    {
        if ($defaults instanceof Closure) {
            $defaults = $defaults();
        }

        static::$defaults = array_merge(static::$defaults, $defaults);
    }

    /** @return array<string, mixed> */
    public static function defaultsFor(string $component): array
    {
        return static::$defaults[$component] ?? [];
    }

    /**
     * Register a scoped personalization.
     *
     * In Blade: <x-wirekit::button scope="rounded">
     */
    public static function scope(string $name, array $personalizations): void
    {
        static::$scoped[$name] = $personalizations;
    }

    /** @return array<string, mixed> */
    public static function scopedFor(string $component, ?string $scope): array
    {
        if ($scope === null) {
            return [];
        }

        return static::$scoped[$scope][$component] ?? [];
    }

    /**
     * Register deep personalization for a component.
     *
     * Replaces entire CSS class blocks:
     *   WireKit::personalize('button', [
     *       'base' => 'inline-flex items-center font-medium',
     *   ]);
     */
    public static function personalize(string $component, array|Closure $blocks): void
    {
        static::$personalizations[$component] = $blocks;
    }

    /** @return array<string, mixed> */
    public static function personalizationFor(string $component): array
    {
        $personalization = static::$personalizations[$component] ?? [];

        if ($personalization instanceof Closure) {
            $personalization = $personalization();
        }

        return $personalization;
    }

    /**
     * Resolve final classes for a component block.
     *
     * Priority chain: deep > scoped > config > component default.
     * This method is called by every component's Blade template.
     */
    public static function resolveClasses(
        string $component,
        string $block,
        string $defaultClasses,
        ?string $scope = null,
    ): string {
        // 1. Deep personalization has highest priority
        $deep = static::personalizationFor($component);
        if (isset($deep[$block])) {
            return $deep[$block];
        }

        // 2. Scoped personalization (e.g. scope="pill")
        $scoped = static::scopedFor($component, $scope);
        if (isset($scoped['classes'][$block])) {
            return $scoped['classes'][$block];
        }

        // 3. Config-based class overrides (wirekit.components.{name}.classes.{block})
        $configClasses = config("wirekit.components.{$component}.classes.{$block}");
        if ($configClasses !== null) {
            return $configClasses;
        }

        // 4. Component default classes (hardcoded in Blade template)
        return $defaultClasses;
    }

    /**
     * Resolve an icon alias to the actual Blade Icon identifier.
     *
     * Usage: WireKit::icon('close') -> 'heroicon-m-x-mark'
     */
    public static function icon(string $alias): string
    {
        return app(IconResolver::class)->resolve($alias);
    }

    /** Get the configured component prefix (default: 'wirekit'). */
    public static function prefix(): string
    {
        return config('wirekit.prefix', 'wirekit');
    }

    /**
     * Reset all personalizations — used in tests only.
     *
     * MUST be called in setUp() of every test to prevent state leakage.
     */
    public static function flush(): void
    {
        static::$defaults = [];
        static::$scoped = [];
        static::$personalizations = [];
    }
}
