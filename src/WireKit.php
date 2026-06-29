<?php

declare(strict_types=1);

namespace Pushery\WireKit;

use Closure;
use Pushery\WireKit\Icons\IconResolver;
use Pushery\WireKit\Support\AvatarPalette;
use Pushery\WireKit\Support\StrictnessGate;

class WireKit
{
    /**
     * Canonical base URL for the public documentation site.
     *
     * Single source of truth for the `https://docs.wirekit.app` literal that
     * the CLI surfaces (wirekit:show / :export-json / :export-api-map /
     * :make / :install / :doctor) emit when pointing developers at a docs
     * page. A future domain change becomes a one-line edit here rather than a
     * scatter-replace across src/Console. No trailing slash — callers append
     * `'/components/'.$name` etc.
     */
    public const DOCS_URL = 'https://docs.wirekit.app';

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
     * Delegates through `StrictnessGate` so the strict-vs-lenient
     * decision is identical across every WireKit validation site
     * (component props here, icon resolution in `IconResolver`).
     *
     * Default behavior (no `wirekit.validation.strict` config):
     *   - APP_DEBUG=true  → throws InvalidArgumentException with Did-you-mean.
     *   - APP_DEBUG=false → logs warning + returns first allowed value.
     *
     * Explicit override: set `wirekit.validation.strict` to true / false
     * (env `WIREKIT_STRICT_VALIDATION`) to force strict / lenient
     * regardless of APP_DEBUG.
     *
     * @param  list<string>  $allowed
     */
    public static function validateProp(
        string $component,
        string $prop,
        string $value,
        array $allowed,
    ): string {
        return StrictnessGate::enforce($component, $prop, $value, $allowed);
    }

    /**
     * Warn at log level when a component receives an unknown prop key
     * (typo for a declared prop, or a use-after-rename). Silent
     * passthrough of `<x-wirekit::button variant="ghost">` (the prop is
     * `surface`, not `variant`) is the bug class — the button silently
     * renders with the default surface and the developer gets no signal
     * that their intended treatment didn't apply.
     *
     * Usage in a Blade component's @php block:
     *
     *     WireKit::warnUnknownProps('button', $attributes->getAttributes(), [
     *         'intent', 'surface', 'size', 'type', 'href', 'disabled',
     *         'loading', 'forceLoading', 'scope',
     *     ]);
     *
     * @param  array<string, mixed>  $actual  The attribute bag (`$attributes->getAttributes()`).
     * @param  list<string>|null  $declared  The declared `@props` keys; when null, derived from the component's own @props.
     */
    public static function warnUnknownProps(string $component, array $actual, ?array $declared = null): void
    {
        StrictnessGate::warnUnknownProps($component, $actual, $declared);
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
     * Deterministic avatar color pair for a key (initials / name).
     *
     * Exposes {@see AvatarPalette::for()} so a
     * developer can color a custom inline avatar/chip with the SAME palette
     * `<x-wirekit::avatar from-initials>` uses, without rendering the
     * component. Returns `['bg' => 'oklch(...)', 'fg' => '#fff']`.
     *
     * @return array{bg: string, fg: string}
     */
    public static function avatarPaletteFor(string $key): array
    {
        return AvatarPalette::for($key);
    }

    /**
     * Return the Tailwind utility string for inline padding at the
     * named tier — the canonical spine-padding emission for components
     * that want to join the page-edge content spine without hand-typing
     * `px-[var(--padding-wk-x-lg)]` (or risking a tier typo).
     *
     * Usage in developer-authored Blade components:
     *
     *     <div class="{{ \Pushery\WireKit\WireKit::spinePadding('lg') }}">
     *         {{-- spine-aligned content --}}
     *     </div>
     *
     * Tiers map 1:1 to the `--padding-wk-x-{tier}` token family. The
     * `lg` tier (default) is the canonical page-edge spine; other
     * tiers (sm / md / xl) are documented in
     * [Theming → Design Token Reference](https://docs.wirekit.app/theming).
     *
     * See [Content-Edge Spine](https://docs.wirekit.app/extending/spine-contract) for the
     * full participation contract.
     */
    public static function spinePadding(string $tier = 'lg'): string
    {
        $allowed = ['sm', 'md', 'lg', 'xl'];
        $validated = in_array($tier, $allowed, true)
            ? $tier
            : self::validateProp('spinePadding', 'tier', $tier, $allowed);

        return match ($validated) {
            'sm' => 'px-[var(--padding-wk-x-sm)]',
            'md' => 'px-[var(--padding-wk-x-md)]',
            'lg' => 'px-[var(--padding-wk-x-lg)]',
            'xl' => 'px-[var(--padding-wk-x-xl)]',
        };
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
     * (`fade-in`, `slide-up-in`, etc). Also accepts the `fade-up` /
     * `fade-down` / `fade-left` / `fade-right` shorthand naming convention
     * as aliases for the corresponding `slide-*-in` presets — the same map
     * `<x-wirekit::reveal>` accepts, kept in lockstep by
     * `FadePresetAliasConsistencyTest`. Unknown values throw via
     * validateProp in debug mode, fall back to the first allowed in
     * production.
     */
    public static function resolveAnimateIn(?string $value, string $component): ?string
    {
        if ($value === null) {
            return null;
        }

        $bases = ['fade', 'slide-up', 'slide-down', 'slide-left', 'slide-right',
            'scale', 'zoom', 'flip', 'rotate', 'bounce', 'spring'];

        // `fade-*` shorthand naming-convention aliases. Resolved BEFORE auto-suffix
        // so `fade-up` resolves to `slide-up-in`, not the non-existent
        // `fade-up-in`. Same map as resources/views/components/reveal.blade.php
        // — divergence is blocked by FadePresetAliasConsistencyTest.
        $aliases = [
            'fade-up' => 'slide-up-in',
            'fade-down' => 'slide-down-in',
            'fade-left' => 'slide-left-in',
            'fade-right' => 'slide-right-in',
        ];
        $value = $aliases[$value] ?? $value;

        // Auto-suffix base names so developers can write `animateIn="fade"`.
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
