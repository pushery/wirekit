<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use InvalidArgumentException;
use Pushery\WireKit\ComponentRegistry;

/**
 * Strictness gate for runtime prop / value validation.
 *
 * Used by WireKit::validateProp() (component-level prop validation) and
 * IconResolver (icon-alias / preset lookups) to decide between two
 * behaviors when an invalid value is supplied:
 *
 *   - STRICT  → throw InvalidArgumentException with a Did-you-mean hint.
 *   - LENIENT → log a warning (with fallback annotation) and return the
 *     first allowed value (or a caller-supplied fallback).
 *
 * Strictness is decided by:
 *   1. Explicit override via `wirekit.validation.strict` config
 *      (env `WIREKIT_STRICT_VALIDATION`) — true/false.
 *   2. Default: APP_DEBUG=true → strict, APP_DEBUG=false → lenient.
 *
 * Throw-on-invalid is a SECOND decision: even in strict mode, the gate
 * only throws when (a) running in console / artisan / Pest, OR (b) the
 * `wirekit.validation.throw_on_invalid` config is explicitly true. In
 * HTTP dev requests (strict + browser), the gate logs at ERROR level
 * and renders the fallback so a single prop typo doesn't 500 the whole
 * blade view. the old
 * always-throw-in-debug behavior took down the entire page on a typo
 * that was purely cosmetic.
 */
final class StrictnessGate
{
    /**
     * HTML global attributes — valid on ANY element, so they can never be a
     * prop typo. Closed set per the WHATWG HTML living standard's "global
     * attributes" section (plus the widely-supported input-hint attributes
     * `autocapitalize` / `autocorrect` that the spec lists as global). Splitting
     * these out of the old ad-hoc `$reserved` grab-bag makes the rule structural:
     * a valid HTML attribute is passthrough by definition, not by whether someone
     * remembered to list it. `inputmode` / `enterkeyhint` sitting here is what
     * stops `<x-wirekit::input inputmode="numeric">` — a correct, accessible
     * mobile-keyboard hint — from logging a spurious "unknown prop" warning.
     *
     * @var list<string>
     */
    public const HTML_GLOBAL_ATTRIBUTES = [
        'id', 'class', 'style', 'title', 'lang', 'dir', 'hidden', 'inert',
        'tabindex', 'accesskey', 'draggable', 'translate', 'contenteditable',
        'spellcheck', 'autocapitalize', 'autocorrect', 'inputmode', 'enterkeyhint',
        'role', 'slot', 'part', 'nonce', 'is', 'autofocus',
    ];

    /**
     * HTML attributes that are valid on form controls / links / media / tables —
     * element-specific rather than global, but equally never a WireKit prop typo
     * when they land in the attribute bag. Kept separate from the global set so
     * each list stays auditable against its spec section.
     *
     * @var list<string>
     */
    public const HTML_ELEMENT_ATTRIBUTES = [
        'name', 'value', 'type', 'placeholder', 'autocomplete', 'disabled',
        'readonly', 'required', 'checked', 'selected', 'multiple', 'min', 'max',
        'step', 'pattern', 'minlength', 'maxlength', 'size', 'for', 'form',
        'method', 'action', 'formaction', 'formmethod', 'novalidate', 'accept',
        'rel', 'target', 'href', 'download', 'ping', 'referrerpolicy',
        'src', 'srcset', 'sizes', 'alt', 'loading', 'decoding', 'width', 'height',
        'poster', 'preload', 'controls', 'muted', 'loop', 'autoplay',
        'colspan', 'rowspan', 'scope', 'headers', 'datetime', 'open', 'cite',
    ];

    /**
     * Ecosystem tooling attributes that are written on a component ON PURPOSE so
     * that they reach the rendered HTML.
     *
     * A third set rather than an entry in either list above, because neither is
     * the right home: these are not HTML-spec attributes, and those two stay
     * auditable against their spec sections.
     *
     * The line is sharp and needs no heuristic. An unknown prop is an
     * instruction that DISAPPEARS -- `variant` on a button lands in the
     * attribute bag, renders as a dead HTML attribute, and the styling the
     * developer meant never happens. A test selector is an instruction that
     * ARRIVES: a browser suite selects on it, which is the entire reason it was
     * written.
     *
     * The prefix list below already covers framework wiring of the same kind --
     * Livewire's `wire:`, Alpine's `x-`, Vue's `v-`. This is the same class of
     * thing for the same ecosystem; it happens to be a closed name rather than a
     * prefix, so it could not ride along there.
     *
     * @var list<string>
     */
    public const TOOLING_ATTRIBUTES = [
        // Laravel Dusk's selector: `<x-wirekit::button dusk="submit-order">`.
        'dusk',
    ];

    /**
     * Attribute-name prefixes that are framework wiring and never a prop.
     *
     * ARIA, data-, Livewire `wire:`, Alpine `x-` / `@` / `:`, Vue `v-`.
     *
     * `on` covers the HTML event-handler family -- onclick, onsubmit, oninput
     * and the rest. They were missing, so a developer writing the perfectly
     * ordinary `<x-wirekit::button onclick="history.back()">` was told their
     * prop was unknown. Measured against a real render before the fix.
     *
     * A prefix rather than an enumeration: the handler list is long, it grows
     * with the platform, and every name in it starts this way. Nothing in the
     * component vocabulary begins with `on`, so it costs no coverage -- the
     * match is prefix-based, so a hypothetical prop named `onset` would slip
     * through, which is a false negative and the safe direction.
     *
     * A CONSTANT, and that is a fix rather than tidying. This list existed
     * twice: once in `unknownPropNames()`, where it decides, and once inside
     * `warnUnknownProps()` under all of the rationale above -- where it had been
     * dead since the verdict was split into its own method. Both copies read as
     * load-bearing. Adding an entry to the wrong one changes nothing at all, and
     * the well-commented copy was the wrong one.
     *
     * @var list<string>
     */
    public const PASSTHROUGH_PREFIXES = ['aria-', 'data-', 'wire:', 'x-', '@', ':', 'v-', 'on'];

    /**
     * Whether an invalid value should THROW rather than degrade to a fallback.
     *
     * Defaults to console / artisan / test (fail-fast — a typo should break the
     * build or the command loudly); an HTTP request degrades so a single bad
     * value cannot 500 a whole view. An explicit `wirekit.validation.throw_on_invalid`
     * config overrides both directions.
     *
     * Exposed so callers that throw their OWN exception (e.g. IconResolver on an
     * unknown alias, which must degrade to a placeholder in an HTTP request
     * instead of taking down every page that renders an icon) can share the same
     * decision without re-implementing it. This is a pure decision helper — it
     * does NOT run enforce()'s validation, so routing IconResolver through it
     * has zero blast radius on the prop-validation path.
     */
    public static function shouldThrowOnInvalid(): bool
    {
        // An explicit config wins in BOTH directions: `true` forces fail-fast
        // even in an HTTP request, `false` forces degradation even in console.
        // Unset (null) falls back to the console/HTTP default.
        $explicit = config('wirekit.validation.throw_on_invalid');
        if ($explicit !== null) {
            return (bool) $explicit;
        }

        return app()->runningInConsole();
    }

    /**
     * Whether the gate currently runs in strict mode.
     */
    public static function isStrict(): bool
    {
        $configured = config('wirekit.validation.strict');
        if ($configured !== null) {
            return (bool) $configured;
        }

        return (bool) config('app.debug');
    }

    /**
     * Validate a value against an allowed list. Throws (strict + CLI/throw-
     * override) or logs+returns the fallback (lenient OR strict-HTTP).
     *
     * @param  list<string>  $allowed
     * @param  string|null  $fallback  Lenient-mode fallback override; defaults to $allowed[0].
     */
    public static function enforce(
        string $context,
        string $key,
        string $value,
        array $allowed,
        ?string $fallback = null,
    ): string {
        if (in_array($value, $allowed, true)) {
            return $value;
        }

        $message = self::formatMessage($context, $key, $value, $allowed);
        $effectiveFallback = $fallback ?? ($allowed[0] ?? '');

        if (self::isStrict()) {
            // Two strict-mode paths:
            //   - CLI / test / explicit throw-on-invalid → throw (fail-fast)
            //   - HTTP dev request → log at ERROR + render fallback
            // The HTTP-dev fall-through a
            // typo in one prop shouldn't 500 the whole blade view.
            if (self::shouldThrowOnInvalid()) {
                throw new InvalidArgumentException($message);
            }

            logger()->error($message.' Falling back to "'.$effectiveFallback.'".');

            return $effectiveFallback;
        }

        // Lenient (prod) — log at warning level and render the fallback.
        logger()->warning(
            $message.' Falling back to "'.$effectiveFallback.'".'
        );

        return $effectiveFallback;
    }

    /**
     * warn on unknown prop KEYS in dev.
     *
     * Pre-fix, Wirekit's prop validation only checked VALUES of declared
     * props — unknown keys (e.g. `<x-wirekit::button variant="ghost">`
     * when the prop is `surface`) were passed through to the attribute
     * bag silently and the component rendered with default `surface=
     * "filled"`. The dev got no signal that their intended `ghost`
     * treatment didn't apply.
     *
     * This helper compares an actual attribute-bag's keys against the
     * declared `@props` keys + a per-component allowlist of legitimate
     * passthrough attribute prefixes (`aria-`, `data-`, `wire:`, `x-`,
     * `@`, plus reserved Blade attrs `style`, `class`, `id`, `name`,
     * `slot`).
     *
     * Unknown keys → log at warning level with a Levenshtein-ranked
     * Did-you-mean hint pointing at the closest declared prop. NEVER
     * throws — silent in prod, visible in dev logs.
     *
     * @param  string  $context  Component name (e.g. `button`, `alert`).
     * @param  array<string, mixed>  $actual  The attribute bag (`$attributes->getAttributes()`).
     * @param  list<string>|null  $declared  The declared `@props` keys; when null, derived from the component's @props.
     */
    public static function warnUnknownProps(string $context, array $actual, ?array $declared = null): void
    {
        // Skip the check outside dev — no value in noisy prod logs over
        // attribute passthroughs the framework supports by design.
        if (! (bool) config('app.debug') && ! app()->runningInConsole()) {
            return;
        }

        // When the declared-prop list isn't passed explicitly, derive it from
        // the component's own @props via the canonical PropsParser-backed
        // registry. A generated list can't drift from the component the way a
        // hand-transcribed one can. Cached per component per request; an
        // unresolvable component (no registry entry / blade file) skips
        // silently rather than throwing.
        if ($declared === null) {
            static $declaredCache = [];
            if (! array_key_exists($context, $declaredCache)) {
                try {
                    // `@props` AND `@aware`, because Blade accepts either name on
                    // the tag and the question here is only "does this component
                    // know it?". `@aware` does not strip its key from the
                    // attribute bag, so a key written directly on the tag arrives
                    // here looking exactly like an unknown prop — and every form
                    // control in the catalog reads `announceErrors` that way.
                    // Deriving from `@props` alone reported the documented call
                    // `<x-wirekit::input announce-errors="false">` as a typo.
                    //
                    // The union lives here and nowhere else: every other reader of
                    // extractProps() means "the props this component declares",
                    // which an `@aware` key is not.
                    $declaredCache[$context] = array_map(
                        static fn (array $p): string => $p['name'],
                        [
                            ...ComponentRegistry::extractProps($context),
                            ...ComponentRegistry::extractAwareProps($context),
                        ],
                    );
                } catch (\Throwable) {
                    $declaredCache[$context] = null;
                }
            }
            $declared = $declaredCache[$context];
            // An unresolvable component (cached null) OR one with no declared
            // @props (cached []) has nothing to validate against — skip rather
            // than flag every attribute as unknown.
            if (empty($declared)) {
                return;
            }
        }

        // The passthrough rule itself lives on `unknownPropNames()` and on the
        // constants it reads. This method used to restate it here in full, and
        // that copy stopped deciding anything the moment the verdict was split
        // out -- see PASSTHROUGH_PREFIXES for why leaving it there was worse
        // than having no comment at all.
        foreach (self::unknownPropNames($actual, $declared) as $key) {
            // Levenshtein-rank against declared props for a Did-you-mean.
            $hint = SuggestSimilar::format(SuggestSimilar::byLevenshtein($key, $declared));
            $message = "WireKit [{$context}]: Unknown prop \"{$key}\". Declared: ".implode(', ', $declared).'.';
            if ($hint !== null) {
                $message .= ' '.$hint;
            }

            logger()->warning($message);
        }

        self::warnScopeDirectiveCollisions($context, $actual);
    }

    /**
     * Warn when a caller passes an Alpine scope directive the component already sets.
     *
     * HTML keeps the FIRST of two identical attributes and discards the rest, so a
     * component that renders `x-data="…"` and `{{ $attributes }}` on the SAME element
     * silently throws away the caller's `x-data`. The caller's scope never comes into
     * existence, and everything they wrote against it — an `x-init` beside it, a
     * `@click` inside it — resolves against the COMPONENT's data instead.
     *
     * It cost a real application a confirmation dialog in front of a destructive action:
     * the dialog stopped opening and the row was deleted without asking. Four things
     * looked at that markup and all four were green — Blade renders a duplicate attribute
     * without complaint because it is valid input, `warnUnknownProps` reads `x-data` as
     * legitimate passthrough because it is, the CSP audit passed 83 expressions, and the
     * runtime error it eventually produced named the CALLER's expression, sending everyone
     * in the wrong direction first.
     *
     * Scoped to the three directives whose loss is not a degradation but a disconnection:
     * `x-data`, `x-init` and `x-modelable` all sever everything downstream from the object
     * it was written against. A duplicate `class` is merged by Blade and a duplicate
     * `aria-*` reads as an intended override; those are not this.
     *
     * 97 of the 259 component views set `x-data`, 5 set `x-init` and 2 set `x-modelable`.
     *
     * @param  array<string, mixed>  $actual  attribute name => value
     */
    private static function warnScopeDirectiveCollisions(string $context, array $actual): void
    {
        $passed = array_values(array_filter(
            array_keys($actual),
            static fn (string $name): bool => in_array($name, ['x-data', 'x-init', 'x-modelable'], true)
        ));

        if ($passed === []) {
            return;
        }

        // The component's own template, read once per component per request. Only reached
        // when a caller actually passed one of the three, so the common path costs nothing.
        static $occupiedCache = [];

        if (! array_key_exists($context, $occupiedCache)) {
            $occupiedCache[$context] = self::scopeDirectivesSetBy($context);
        }

        $occupied = $occupiedCache[$context];

        foreach ($passed as $directive) {
            if (! in_array($directive, $occupied, true)) {
                continue;
            }

            logger()->warning(sprintf(
                'WireKit [%s]: your `%s` is DISCARDED — the component sets its own on the same '.
                'element, and HTML keeps the first of two identical attributes. Your scope never '.
                'exists, so anything written against it resolves against the component\'s data '.
                'instead. Wrap the component in your own element, or use the methods the '.
                'component already exposes.',
                $context,
                $directive
            ));
        }
    }

    /**
     * Which scope directives a component's own template sets.
     *
     * Read from the Blade source rather than declared by hand: a hand-kept list is one more
     * thing to forget, and the template is the authority for what it renders. Anything
     * unreadable yields an empty list — a missing warning is better than a throw inside a
     * component's render path.
     *
     * @return list<string>
     */
    private static function scopeDirectivesSetBy(string $context): array
    {
        try {
            $path = ComponentRegistry::existingBladeFilePath($context);
        } catch (\Throwable) {
            return [];
        }

        if ($path === null || ! is_file($path)) {
            return [];
        }

        $source = (string) file_get_contents($path);

        return array_values(array_filter(
            ['x-data', 'x-init', 'x-modelable'],
            static fn (string $directive): bool => preg_match('/\s'.preg_quote($directive, '/').'\s*=/', $source) === 1
        ));
    }

    /**
     * The attribute names in `$actual` that are neither declared props nor legitimate
     * passthrough. The VERDICT, with no logging and no environment gate.
     *
     * Split out of `warnUnknownProps()` because a second caller needs the same answer for
     * a different purpose: a guard over the Blade snippets in the README and in PHP
     * docblocks. Those snippets are the most-copied lines the package has, and one of them
     * taught `variant` on `button` — a prop the component does not declare, which Blade
     * folds into the attribute bag where it renders as a literal HTML attribute nothing
     * reads. The page looked finished, no test failed, and the `Delete` button rendered in
     * the ACCENT color: a destructive action styled as the primary call to action.
     *
     * `warnUnknownProps()` could not have caught it. It only logs, so nothing can fail on
     * it, and it returns early outside `app.debug` — so in production the signal does not
     * exist at all. Hence a predicate a test can assert on, and the two share this one
     * implementation so the rule cannot drift between them.
     *
     * @param  array<string, mixed>  $actual  attribute name => value
     * @param  list<string>  $declared  the component's declared @props
     * @return list<string> unknown names, in encounter order
     */
    public static function unknownPropNames(array $actual, array $declared): array
    {
        // Nothing to validate against — say so here rather than in every caller.
        //
        // A component with no resolvable `@props` (`glass`, `fonts`) yields an empty declared
        // list, and against an empty list EVERY attribute is unknown. `warnUnknownProps()`
        // has always returned early on exactly this case; the predicate did not, so each
        // caller carried its own `if ($declared === []) continue;` and one that forgot got a
        // wave of phantom findings rather than a quiet pass.
        //
        // Reported by a downstream repo that adopted this predicate to replace its own
        // hand-rolled walker and had to preserve the guard by hand. A rule that every caller
        // must remember is a rule that one of them will not.
        if ($declared === []) {
            return [];
        }

        // A valid HTML attribute is passthrough by definition, not by whether it
        // was remembered in an ad-hoc list -- hence the spec-derived sets. The
        // tooling set carries the attributes whose purpose IS to reach the HTML.
        $reserved = [
            ...self::HTML_GLOBAL_ATTRIBUTES,
            ...self::HTML_ELEMENT_ATTRIBUTES,
            ...self::TOOLING_ATTRIBUTES,
        ];
        $prefixes = self::PASSTHROUGH_PREFIXES;

        $unknown = [];

        foreach (array_keys($actual) as $key) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            if (in_array($key, $declared, true) || in_array($key, $reserved, true)) {
                continue;
            }
            foreach ($prefixes as $p) {
                if (str_starts_with($key, $p)) {
                    continue 2;
                }
            }

            // `show-value` and `showValue` are the same prop: Blade camel-cases a kebab
            // attribute name before matching it against `@props`, so a component that
            // declares `showValue` is correctly addressed either way.
            //
            // This is inert at runtime and load-bearing for the static callers. At runtime
            // the conversion has ALREADY happened by the time an attribute reaches the bag,
            // so a declared prop never arrives here in kebab form and this branch cannot
            // fire. A guard reading a Blade SNIPPET sees the author's spelling, so without
            // this it reports `show-value` as undeclared — a false positive on correct
            // documentation, which is the failure mode that costs the most trust.
            //
            // CALLERS MUST PASS THE RAW ATTRIBUTE NAME, not a pre-camel-cased one. The
            // prefix skips above are the reason: `aria-label` camel-cases to `ariaLabel`
            // and `x-on:click` to `xOn:click`, neither of which starts with `aria-` or
            // `x-` any more, so a caller that normalizes first defeats every passthrough
            // rule at once. Measured, not reasoned: a scan of 1332 snippet usages that
            // normalized before calling reported 77 findings against this function's 4,
            // and all 40 additions were correct `aria-*` / `x-*` / `data-*` attributes.
            //
            // Doing the conversion HERE rather than at the call site is what makes that
            // mistake unavailable — there is nothing left for a caller to normalize.
            if (in_array(lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $key)))), $declared, true)) {
                continue;
            }

            $unknown[] = $key;
        }

        return $unknown;
    }

    /**
     * Build the canonical "Invalid X" message with Did-you-mean hint.
     * Exposed so callers that throw their own exception (e.g.
     * IconResolver) can reuse the exact wording without re-implementing
     * the Levenshtein-suggestion contract.
     *
     * @param  list<string>  $allowed
     */
    public static function formatMessage(
        string $context,
        string $key,
        string $value,
        array $allowed,
    ): string {
        $list = implode(', ', $allowed);
        $message = "WireKit [{$context}]: Invalid {$key} \"{$value}\". Allowed: {$list}.";

        $hint = SuggestSimilar::format(SuggestSimilar::byLevenshtein($value, $allowed));
        if ($hint !== null) {
            $message .= ' '.$hint;
        }

        return $message;
    }
}
