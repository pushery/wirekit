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
     * Validate a prop value against a list of allowed values.
     *
     * In debug (APP_DEBUG=true): throws InvalidArgumentException.
     * In production: logs a warning and falls back to the first allowed value.
     *
     * @param  list<string>  $allowed
     */
    public static function validateProp(
        string $component,
        string $prop,
        string $value,
        array $allowed,
    ): string {
        if (in_array($value, $allowed, true)) {
            return $value;
        }

        $list = implode(', ', $allowed);
        $message = "WireKit [{$component}]: Invalid {$prop} \"{$value}\". Allowed: {$list}.";

        if (config('app.debug')) {
            throw new \InvalidArgumentException($message);
        }

        logger()->warning($message);

        return $allowed[0];
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

    /**
     * Resolves the `animateIn` prop on marketing components into an x-data
     * attribute string for the wirekitAnimate Alpine helper, or null when
     * the prop is unset (default — no animation, byte-identical to v1.5.0).
     *
     * Accepts both base names (`fade` → `fade-in`) and full preset names
     * (`fade-in`, `slide-up-in`, etc). Unknown values throw via validateProp
     * in debug mode, fall back to the first allowed in production.
     */
    public static function resolveAnimateIn(?string $value, string $component): ?string
    {
        if ($value === null) {
            return null;
        }

        $bases = ['fade', 'slide-up', 'slide-down', 'slide-left', 'slide-right',
            'scale', 'zoom', 'flip', 'rotate', 'bounce', 'spring'];

        // Auto-suffix base names so consumers can write `animateIn="fade"`.
        if (in_array($value, $bases, true)) {
            $value = $value.'-in';
        }

        $allowed = array_merge(
            array_map(fn ($p) => $p.'-in', $bases),
            array_map(fn ($p) => $p.'-out', $bases)
        );

        $validated = in_array($value, $allowed, true)
            ? $value
            : self::validateProp($component, 'animateIn', $value, $allowed);

        return sprintf('x-data="wirekitAnimate(\'%s\')"', $validated);
    }
}
