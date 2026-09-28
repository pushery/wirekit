<?php

declare(strict_types=1);

namespace Pushery\WireKit;

use Closure;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Pushery\WireKit\Icons\IconResolver;
use Pushery\WireKit\Support\AvatarPalette;
use Pushery\WireKit\Support\StrictnessGate;

class WireKit
{
    /**
     * Canonical base URL for the public documentation site.
     *
     * The one place the CLI surfaces (wirekit:show / :export-json /
     * :export-api-map / :make / :install / :doctor) take the
     * `https://docs.wirekit.app` literal from when they point developers at a
     * docs page. No trailing slash — callers append `'/components/'.$name` etc.
     *
     * The recipe stubs under `src/Console/stubs/recipes/` carry the literal in
     * their headers all the same: they are copied into the application as
     * written, so a comment there cannot read a constant. A domain change
     * therefore edits this line and those headers.
     */
    public const DOCS_URL = 'https://docs.wirekit.app';

    /**
     * The components whose template resolves no class block of its own, and why.
     *
     * Every other component template passes its classes through `resolveClasses()`, directly or
     * through a partial, so a personalization, a scope and the `wirekit.components.<name>.classes`
     * config reach it. These render no element of their own that carries classes, so nothing
     * would read a personalization registered under their name: `personalize()` says so in
     * debug, and each reason names where the classes are resolved instead.
     *
     * @var array<string, string>
     */
    public const COMPONENTS_WITHOUT_CLASS_BLOCKS = [
        'faq-item' => 'it renders `accordion.item`, which resolves the classes; personalize `accordion.item` instead.',
        'fonts' => 'it renders `<link>` and `<style>` tags and no styled element.',
        'glass' => 'it renders the stylesheet, the script and a hidden SVG filter definition, and no styled element.',
        'structured-data' => 'it renders a JSON-LD script and no styled element.',
        'toggle-button' => 'it renders `button` with its own `scope`, which resolves the classes; personalize `button` or a scope of it instead.',
    ];

    /** @var array<string, array<string, mixed>> */
    protected static array $defaults = [];

    /** @var array<string, array<string, array<string, mixed>>> */
    protected static array $scoped = [];

    /** @var array<string, Closure|array<string, mixed>> */
    protected static array $personalizations = [];

    /**
     * Define global defaults for components.
     *
     * Usage in AppServiceProvider::boot():
     *   WireKit::defaults([
     *       'button' => ['intent' => 'primary', 'size' => 'md'],
     *       'input'  => ['size' => 'lg'],
     *   ]);
     *
     * Every key has to be a prop the component actually declares. Blade folds an
     * unknown one into the attribute bag, where it renders as a literal HTML
     * attribute that nothing reads — so the page looks finished and no test fails.
     * This docblock taught `variant` on both of these for exactly that reason:
     * `button` migrated to `intent` + `surface` and `input` never had a `variant`
     * at all.
     *
     * `variant` is NOT retired in general — it is a live, declared prop across a large
     * part of the catalog, and it is retired on `button` and `badge` ONLY. A blanket
     * search-and-replace across `variant=` therefore breaks every component that is
     * correct.
     *
     * The set is not listed here, because a list that names some of them reads as an
     * inventory. The parser answers it, and cannot drift:
     *
     *     collect(array_keys(ComponentRegistry::all()))->filter(fn ($name) => in_array(
     *         'variant', array_column(ComponentRegistry::extractProps($name), 'name'), true
     *     ))
     *
     * @param  array<string, mixed>|Closure  $defaults
     */
    public static function defaults(array|Closure $defaults): void
    {
        if ($defaults instanceof Closure) {
            $defaults = $defaults();
        }

        static::$defaults = array_merge(static::$defaults, $defaults);

        /*
         * Feed the same config the components already read.
         *
         * This method stored its values and nothing ever asked for them: no template called
         * `defaultsFor()`, so a documented feature did precisely nothing. The obvious repair
         * was to teach every template to consult it — and that would have been the wrong one,
         * because they ALREADY resolve a default this way:
         *
         *     @props(['intent' => config('wirekit.components.button.intent', 'primary')])
         *
         * So the mechanism was never missing. There were two of them for one job, and only
         * one was connected. Writing into that config wires this everywhere at once, with no
         * per-component edits and no second resolution order to keep in step with the first.
         *
         * Runtime beats the published file, which is the right way round: a developer calling
         * this in a service provider is being more specific than their config file, exactly as
         * a later `config()->set()` is.
         *
         * The stored array is kept as well, because `defaultsFor()` is public and something
         * may read it. It is now a record of what was set, not the thing that has the effect.
         */
        foreach ($defaults as $component => $props) {
            if (! is_array($props)) {
                continue;
            }

            foreach ($props as $prop => $value) {
                // A sub-component's PROP is read by its template through the dotted path,
                // `config('wirekit.components.dropdown.panel.width')`, and config() walks that
                // one segment at a time: 'dropdown', then 'panel'. So the value is written
                // there, nested under the parent. Under the literal key 'dropdown.panel' it
                // would be the silent no-op this method was rescued from once already.
                if ($prop !== 'classes' && str_contains((string) $component, '.')) {
                    config(["wirekit.components.{$component}.{$prop}" => $value]);

                    continue;
                }

                // A top-level component, and `classes` of any component, keep the literal key.
                // For a top-level name the two forms are the same thing. For `classes` the
                // literal key is the one `resolveClasses()` reads first, and the shipped config
                // declares every sub-component that way ('card.header' => []).
                $components = config('wirekit.components', []);
                $components[$component] = array_merge(
                    is_array($components[$component] ?? null) ? $components[$component] : [],
                    [$prop => $value],
                );

                config(['wirekit.components' => $components]);
            }
        }
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
     *
     * @param  array<string, mixed>  $personalizations
     */
    public static function scope(string $name, array $personalizations): void
    {
        static::$scoped[$name] = $personalizations;
    }

    /**
     * @internal Reads back one component's scoped personalization.
     *
     * Public without an external caller: the only call site is `resolveClasses()`
     * in this class. It is not a promise — a developer registers a scope with
     * `WireKit::scope()` and reads the result through the rendered component.
     *
     * @return array<string, mixed>
     */
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
     *
     * A component in {@see self::COMPONENTS_WITHOUT_CLASS_BLOCKS} resolves no block of its own,
     * so nothing reads a personalization registered under its name. That is logged as a warning
     * in debug and on the console, where the developer is, and not in production.
     *
     * @param  array<string, mixed>|Closure  $blocks
     */
    public static function personalize(string $component, array|Closure $blocks): void
    {
        static::$personalizations[$component] = $blocks;

        $reason = self::COMPONENTS_WITHOUT_CLASS_BLOCKS[$component] ?? null;

        if ($reason !== null && ((bool) config('app.debug') || app()->runningInConsole())) {
            logger()->warning("[WireKit] WireKit::personalize('{$component}') has no effect: {$reason}");
        }
    }

    /**
     * Every component that carries a personalization, by name.
     *
     * `personalizationFor()` answers for a component you already suspect. Nothing
     * could answer "which components has this application personalized at all",
     * which is the question a diagnostic has to start from — it cannot guess the
     * names. Reading only; the map itself stays protected.
     *
     * @return list<string>
     */
    public static function personalizedComponents(): array
    {
        return array_keys(static::$personalizations);
    }

    /**
     * @internal Reads back one component's deep personalization.
     *
     * Public because `VerifyInstallationCommand` calls it from another class to
     * report which blocks an application replaced. Not a promise to a developer;
     * the supported way in is `WireKit::personalize()`.
     *
     * @return array<string, mixed>
     */
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
     * Every component template calls it for each element that carries classes, directly or
     * through a partial; the few that render no such element are listed, with the reason, in
     * {@see self::COMPONENTS_WITHOUT_CLASS_BLOCKS}.
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
            return self::applyBlock($deep[$block], $defaultClasses);
        }

        // 2. Scoped personalization (e.g. scope="pill")
        $scoped = static::scopedFor($component, $scope);
        if (isset($scoped['classes'][$block])) {
            return self::applyBlock($scoped['classes'][$block], $defaultClasses);
        }

        // 3. Config-based class overrides (wirekit.components.{name}.classes.{block}).
        // A sub-component name carries a dot ('sidebar.item', 'card.header', 'table.th'),
        // and the shipped config declares these as LITERAL dotted keys. config()/Arr::get()
        // splits the key on '.', so config("wirekit.components.sidebar.item.classes.{block}")
        // walks components -> 'sidebar' -> 'item' and never reaches the literal 'sidebar.item'
        // key — the override silently no-ops for every dotted sub-component (the larger part of
        // the catalog; top-level names without a dot always worked). Read the components map
        // once and index the literal component key directly, then fall back to the dotted
        // config() path so a developer who used the accidental nested-array form still resolves.
        // Both forms work -> backward compatible. Do NOT "simplify" this back to a bare config().
        $components = config('wirekit.components', []);
        $configClasses = $components[$component]['classes'][$block]
            ?? config("wirekit.components.{$component}.classes.{$block}");
        if ($configClasses !== null) {
            return $configClasses;
        }

        // 4. Component default classes (hardcoded in Blade template)
        return $defaultClasses;
    }

    /**
     * Resolve one personalized block value against the vendor default.
     *
     * A block value is normally the finished class string, and that REPLACES the
     * shipped block outright. Replacement is a reasonable default and a poor only
     * option: changing one color or one measurement means restating the whole
     * block, and from that moment the application owns it — every later
     * improvement to that block silently stops arriving. Nothing reports it,
     * because nothing is wrong; the personalization simply looks like a decision
     * somebody made, for as long as it stands.
     *
     * So a block value may instead be a closure, and it RECEIVES the vendor
     * default:
     *
     *     WireKit::personalize('sidebar.item', [
     *         'base' => fn (string $vendor): string => $vendor.' rounded-none',
     *     ]);
     *
     * That is additive without being a second API: the same key, the same call,
     * one different value shape. The application states only its own delta and
     * keeps inheriting the rest.
     *
     * Strings stay exactly as they were — a closure was previously a TypeError
     * against this method's `string` return, so nothing that worked can change
     * meaning here.
     *
     * Deliberately NOT extended to the config layer (#3 in the chain): a closure
     * in a config file cannot survive `config:cache`, so offering it there would
     * hand developers a personalization that works until the first cached deploy.
     */
    // `self::`, not `static::`. Late static binding on a PRIVATE method is unsafe —
    // `static::` resolves to the called class, where a private member of THIS one is
    // not visible. PHPStan calls it out (staticClassAccess.privateMethod) and it is
    // right: the surrounding calls use `static::` because those methods are public.
    private static function applyBlock(mixed $value, string $vendorClasses): string
    {
        if ($value instanceof Closure) {
            return (string) $value($vendorClasses);
        }

        // Returned as-is, NOT cast. The file is strict_types, so this method's
        // `string` return already rejects a block value of the wrong type — a
        // number or an array raises the same TypeError it always did. Casting here
        // would have quietly turned those into "5" and "Array", relaxing an
        // existing contract as a side effect of adding an unrelated feature.
        return $value;
    }

    /**
     * @internal Seeds a morph-stable DOM id for a component template.
     *
     * Public without an external caller: every call site is a Blade view in this package
     * (`tabs`, `collapsible`, `data-table`, `filter-builder`, `notification-center`,
     * `status-matrix`, `sidebar/collapsible`, `sidebar/group`), and a view cannot reach a
     * non-public static. It is plumbing for those templates, not a promise a developer may
     * build on.
     *
     * Read that list as which kind of thing calls this, never as an inventory: a docblock
     * enumerating call sites is a second copy of `grep`, and the copy is the one that goes
     * stale.
     *
     * A DOM id that survives a Livewire round trip.
     *
     * An Alpine widget root whose id is re-randomized on every render is a different
     * element to Livewire's morph, so the morph destroys and rebuilds it — and every
     * piece of Alpine-only state goes with it: a sorted column, a hidden column, an open
     * panel. The user sorts, clicks anything that talks to the server, and the sort is
     * gone. Nothing errors; the widget simply forgets.
     *
     * A caller-supplied `id` solves it. What this fixes is the DEFAULT, by seeding it from the component's own stable identity — its `name` — which
     * is precisely what a widget embedded in a Livewire component is given anyway.
     *
     * Without a seed there is still something stable: the order the widgets render in.
     * {@see DomId} counts per request and per prefix, so two unnamed widgets get `-1` and `-2`
     * (no collision) and the same widget gets the same number on the next render (stable).
     * The registry resets on `RequestHandled`, so it is Octane-safe rather than accidentally
     * correct under FPM. A random suffix instead would rebuild the widget on every render: an
     * open panel's node disconnected after one `$refresh()`, a closed replacement in its place,
     * and a trigger that never opens again, with no error and no warning.
     *
     * The caveat DomId documents travels with the delegation and is the right trade: across
     * independently-updating Livewire islands the counter can restart, so an unnamed widget in
     * an island that re-renders alone may come back with a different number. A caller who hits
     * that passes a `name`, which is the one-word fix. A random id breaks the pairing on every
     * render; a counted one only in that case.
     */
    public static function stableId(string $prefix, ?string $seed = null): string
    {
        if ($seed === null || trim($seed) === '') {
            return Support\DomId::unique(null, $prefix.'-');
        }

        $slug = Str::slug($seed);

        // A seed of nothing but punctuation slugs to an empty string, which would make
        // every such widget share one id.
        return $slug === ''
            ? $prefix.'-'.substr(hash('sha256', $seed), 0, 8)
            : $prefix.'-'.$slug;
    }

    /**
     * @internal Validates the `as` prop for a component template.
     *
     * Public without an external caller: every call site is a Blade view in this package,
     * and a view cannot reach a non-public static. A developer sets `as` on the component;
     * they do not call this.
     *
     * Validate a developer-supplied HTML tag name before it is rendered as one.
     *
     * A component that accepts `as` interpolates it straight into its opening tag — either
     * as `<{{ $as }} …>` or as `<{{ $tag }} …>`, where a default was resolved first. Blade
     * escapes that echo, and `e()` escapes NEITHER A SPACE NOR AN `=`, so
     * `as="div onmouseover=alert(1)"` renders `<div onmouseover=alert(1) class="…">`: a
     * working event handler. Measured across text, container, row, stack, center and
     * section, all six identical.
     *
     * The covered set is derived, never restated here as a number: every component that
     * declares an `as` prop and interpolates a variable into its opening tag either calls
     * this, or validates against a closed enum of its own.
     *
     * `as` is developer-supplied rather than end-user input, which is why this is a hole
     * rather than a live exploit — but a value derived from data (a CMS block type, a
     * per-tenant layout setting) reaches it the same way, and nothing said it must not.
     *
     * The SHAPE is checked rather than an allowlist of tags, deliberately. An enum would
     * have to guess which elements a developer legitimately wants — `article`, `aside`,
     * `figure`, a custom element — and break the ones it did not think of. A tag name has
     * exactly one shape, and nothing that satisfies it can carry an attribute.
     *
     * @throws \InvalidArgumentException when the value cannot be a tag name
     */
    public static function tagName(string $component, string $as): string
    {
        // A letter, then letters, digits or hyphens. That admits every HTML element and
        // every valid custom element, and admits no whitespace, quote, slash or equals.
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9-]*$/', $as) === 1) {
            return $as;
        }

        throw new \InvalidArgumentException(sprintf(
            'WireKit [%s]: `as` must be an HTML tag name, got "%s". Anything with a space or '.
            'an `=` in it is rendered into the opening tag as an attribute.',
            $component,
            $as
        ));
    }

    /**
     * Validate a prop value against a list of allowed values.
     *
     * Delegates through `StrictnessGate` so the strict-vs-lenient
     * decision is identical across every WireKit validation site
     * (component props here, icon resolution in `IconResolver`).
     *
     * Default behavior (neither `wirekit.validation.strict` nor
     * `wirekit.validation.throw_on_invalid` set):
     *   - APP_DEBUG=true, console / test run → throws InvalidArgumentException
     *     with Did-you-mean.
     *   - APP_DEBUG=true, HTTP request       → logs an error + returns the first
     *     allowed value.
     *   - APP_DEBUG=false                    → logs a warning + returns the first
     *     allowed value.
     *
     * Explicit overrides: `wirekit.validation.strict` (env
     * `WIREKIT_STRICT_VALIDATION`) forces strict / lenient regardless of
     * APP_DEBUG, and `wirekit.validation.throw_on_invalid` (env
     * `WIREKIT_THROW_ON_INVALID`) decides whether a strict miss throws. A
     * lenient miss never throws. The full matrix is StrictnessGate's docblock.
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
     * The CSP nonce this request runs under, or null when it runs under none.
     *
     * WireKit emits exactly one inline `<style>` — the three font custom
     * properties, which cannot be baked into the shipped stylesheet because they
     * are derived from the application's own font configuration rather than from
     * the build. Under a policy without `'unsafe-inline'` that block needs a nonce
     * or it is discarded.
     *
     * And it is discarded abruptly, which is why this resolves itself rather than
     * waiting to be handed a value. From CSP Level 2 on, a nonce anywhere in a
     * directive makes the browser ignore `'unsafe-inline'` in that same directive —
     * so the moment an application adds a nonce to `style-src` for any reason at
     * all, this block loses the permission it had. The browser reports the refusal
     * as a policy violation and nothing on the page looks broken: it renders, and
     * the typography falls back to the system font. That failure passes every HTML
     * comparison and every
     * header assertion, so requiring the developer to remember one more parameter
     * would be requiring them to remember the thing they cannot see going wrong.
     *
     * Two sources, in order. The container binding is what the rest of the fleet
     * publishes; `Vite::cspNonce()` is what Livewire itself reads, so honoring it
     * means a Laravel application that already has a nonce needs no configuration
     * here at all.
     *
     * An explicit `nonce` prop still wins over both — an application that mints a
     * value per response and does not publish it anywhere can pass it directly.
     */
    public static function cspNonce(): ?string
    {
        if (app()->bound('csp-nonce')) {
            $bound = app('csp-nonce');

            if (is_string($bound) && $bound !== '') {
                return $bound;
            }
        }

        $vite = Vite::cspNonce();

        return is_string($vite) && $vite !== '' ? $vite : null;
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

    /**
     * Is this name a declared icon alias?
     *
     * Usage: WireKit::isIconAlias('webhook') -> true
     *
     * `icon()` cannot answer this. It always returns something — an unknown name falls
     * through to the icon set's own naming — so it tells you what will render, never
     * whether WireKit knew the name. This one says no.
     */
    public static function isIconAlias(string $name): bool
    {
        return app(IconResolver::class)->isAlias($name);
    }

    /**
     * The whole declared icon vocabulary, alias => blade-icons identifier.
     *
     * Usage: WireKit::iconVocabulary() -> ['close' => 'heroicon-m-x-mark', …]
     *
     * What a tool needs to offer completion, or to check a design system's names
     * against the ones that actually exist here.
     *
     * @return array<string, string>
     */
    public static function iconVocabulary(): array
    {
        return app(IconResolver::class)->vocabulary();
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
            // Unreachable through the gate above, which returns a member of $allowed or the
            // lenient fallback. Present because PHPStan cannot see that, and written as the
            // SAME fallback rather than a throw: the gate deliberately does not let one
            // mistyped prop take down a whole Blade view, and a throw here would undo that
            // decision from a place nobody would think to look.
            default => 'px-[var(--padding-wk-x-md)]',
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
        Support\DomId::reset();
        StrictnessGate::forgetLogged();
        Support\TourStepCounter::reset();
        Support\FaqCollector::reset();
    }

    /**
     * Resolves the `animateIn` prop on marketing components into an x-data
     * attribute string for the wirekitAnimate Alpine helper, or null when
     * the prop is unset: the default, with no animation and no attribute in the markup.
     *
     * Accepts both base names (`fade` → `fade-in`) and full preset names
     * (`fade-in`, `slide-up-in`, etc). Also accepts the `fade-up` /
     * `fade-down` / `fade-left` / `fade-right` shorthand naming convention
     * as aliases for the corresponding `slide-*-in` presets — the same map
     * `<x-wirekit::reveal>` accepts, kept in lockstep by
     * `FadePresetAliasConsistencyTest`. Unknown values are reported through
     * validateProp and the strictness gate, and fall back to the first allowed
     * value wherever the gate does not throw.
     *
     * @internal Public because nine Blade templates call it while rendering —
     * alert, callout, card, cta, empty-state, feature, footer, hero and stat.
     * A developer sets `animate-in` on the component and never calls this.
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
