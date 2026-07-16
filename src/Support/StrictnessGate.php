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
     * mobile-keyboard hint — from logging a spurious "unknown prop" warning
     * (WIRE-111).
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
                    $declaredCache[$context] = array_map(
                        static fn (array $p): string => $p['name'],
                        ComponentRegistry::extractProps($context),
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

        // Allowed passthrough: any valid HTML attribute (global or element-
        // specific) is never a WireKit prop typo, so it passes unflagged. The
        // two closed sets are declared as class constants above so each stays
        // auditable against its spec section — a valid attribute is passthrough
        // by definition, not by whether it was remembered in an ad-hoc list.
        $reserved = [...self::HTML_GLOBAL_ATTRIBUTES, ...self::HTML_ELEMENT_ATTRIBUTES];
        // Prefix-matched passthrough: ARIA, data-, Livewire wire:, Alpine
        // x-/@/:, Vue v-. Any attribute starting with one of these is framework
        // wiring, never a prop.
        $prefixes = ['aria-', 'data-', 'wire:', 'x-', '@', ':', 'v-'];

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

            // Levenshtein-rank against declared props for a Did-you-mean.
            $hint = SuggestSimilar::format(SuggestSimilar::byLevenshtein($key, $declared));
            $message = "WireKit [{$context}]: Unknown prop \"{$key}\". Declared: ".implode(', ', $declared).'.';
            if ($hint !== null) {
                $message .= ' '.$hint;
            }

            logger()->warning($message);
        }
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
