<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use InvalidArgumentException;
use Pushery\WireKit\ComponentRegistry;
use Pushery\WireKit\WireKit;

/**
 * Strictness gate for runtime prop / value validation.
 *
 * Used by WireKit::validateProp() (component-level prop validation) and
 * IconResolver (icon-alias / preset lookups) to decide what happens when an
 * invalid value is supplied. For a prop value, one of three things:
 *
 *   - strict, and throwing on → throw InvalidArgumentException with a
 *     Did-you-mean hint.
 *   - strict, throwing off     → log at ERROR level and return the fallback.
 *   - lenient                  → log a warning (with fallback annotation) and
 *     return the first allowed value (or a caller-supplied fallback).
 *
 * Strictness is decided by:
 *   1. Explicit override via `wirekit.validation.strict` config
 *      (env `WIREKIT_STRICT_VALIDATION`) — true/false.
 *   2. Default: APP_DEBUG=true → strict, APP_DEBUG=false → lenient.
 *
 * Throwing is a SECOND decision, and it only matters while the gate is strict:
 *   1. Explicit override via `wirekit.validation.throw_on_invalid` config
 *      (env `WIREKIT_THROW_ON_INVALID`) — true throws even in an HTTP request,
 *      false never throws, not even in console / artisan / Pest.
 *   2. Default: console / artisan / Pest → throw, HTTP request → do not.
 * So an HTTP request with APP_DEBUG=true logs at ERROR level and renders the
 * fallback, and a single prop typo does not 500 the whole Blade view. That
 * split replaced an always-throw-in-debug behavior which took down the entire
 * page on a typo that was purely cosmetic. IconResolver reads the second
 * decision alone, for the reason on shouldThrowOnInvalid().
 */
final class StrictnessGate
{
    /**
     * The directives whose loss is a disconnection rather than a degradation.
     *
     * Each of these severs everything downstream from the object it was written against: an
     * `x-init` beside a discarded `x-data`, a `@click` inside it. A duplicate `class` is
     * merged by Blade and a duplicate `aria-*` reads as an intended override — those are not
     * this, which is why the list is short and closed.
     *
     * One list, read by the predicate and by the reader of the component's own template, so
     * the two cannot come to disagree about which directives the rule covers.
     *
     * `x-effect` and `x-model` were added after an adopting application reported that its own
     * copy of this guard had needed them, and its reasoning holds here. `x-effect` is what a
     * caller reaches for when a component's own `x-init` cannot follow a change — that is, the
     * very tool the first instance of this defect calls for, so repairing one collision runs
     * straight into the next. And a discarded `x-model` is a two-way binding that silently
     * never binds, which is the quietest failure of the five: a discarded `x-data` is visible
     * because nothing happens at all, while a discarded `x-model` looks like a field that just
     * does not change.
     *
     * @var list<string>
     */
    private const SCOPE_DIRECTIVES = ['x-data', 'x-init', 'x-effect', 'x-model', 'x-modelable'];

    /**
     * Global attributes — valid on ANY element, so they can never be a prop typo.
     *
     * The HTML set is the WHATWG living standard's, taken from its index of attributes: every
     * row whose element column reads "HTML elements". That is 31 names, the microdata family,
     * `popover` and `writingsuggestions` among them, and it was 22 of them until an audit
     * compared the two. `<x-wirekit::card popover id="…">` logged an unknown prop.
     *
     * Two names come from other specifications that make them global as well, and they are
     * listed apart so the WHATWG half stays checkable against its index: `role` (WAI-ARIA, which
     * HTML allows on every element) and `part` (CSS Shadow Parts).
     *
     * A valid attribute is passthrough by definition, not by whether someone remembered to list
     * it: `inputmode` and `enterkeyhint` here are what stop `<x-wirekit::input inputmode="numeric">`,
     * a correct mobile-keyboard hint, from logging a spurious warning.
     *
     * @var list<string>
     */
    public const HTML_GLOBAL_ATTRIBUTES = [
        // WHATWG HTML, "HTML elements" in the index of attributes.
        'accesskey', 'autocapitalize', 'autocorrect', 'autofocus', 'class', 'contenteditable',
        'dir', 'draggable', 'enterkeyhint', 'headingoffset', 'headingreset', 'hidden', 'id',
        'inert', 'inputmode', 'is', 'itemid', 'itemprop', 'itemref', 'itemscope', 'itemtype',
        'lang', 'nonce', 'popover', 'slot', 'spellcheck', 'style', 'tabindex', 'title',
        'translate', 'writingsuggestions',
        // Global by another specification: WAI-ARIA and CSS Shadow Parts.
        'role', 'part',
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
        'rel', 'target', 'href', 'download', 'ping', 'referrerpolicy', 'hreflang', 'media',
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
     * Names Blade itself takes out of a component tag before any of it reaches the bag.
     *
     * `:attributes="$bag"` hands a whole attribute bag on to a component, and it is the form
     * `{{ $attributes }}` inside a tag compiles to. Blade merges that bag into the attributes
     * and drops the key, so at render time no attribute called `attributes` ever exists. A
     * reader of the TEMPLATE sees the name in the tag, though, and without this entry it
     * would report correct Blade as an unknown prop.
     *
     * @var list<string>
     */
    public const BLADE_ATTRIBUTES = [
        'attributes',
    ];

    /**
     * Attribute-name prefixes that are framework wiring and never a prop.
     *
     * ARIA, data-, Livewire `wire:`, Alpine `x-` / `@` / `:`, Vue `v-`.
     *
     * `on` covers the HTML event-handler family -- onclick, onsubmit, oninput
     * and the rest. They were missing, so a developer writing the perfectly
     * ordinary `<x-wirekit::button onclick="history.back()">` would be told their
     * prop was unknown.
     *
     * A prefix rather than an enumeration: the handler list is long, it grows
     * with the platform, and every name in it starts this way. It is the one
     * prefix that is NOT a family on its own, because three props begin with the
     * same two letters: `toggle-button`'s `onIcon` and `onLabel`, and `reveal`'s
     * `once`. {@see self::isPassthroughName()} tells the two apart.
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
     * The most rejections one set remembers. A process that renders outside a request and a
     * queued job, such as a command sending mail in a loop, never resets the set; at this size
     * it starts over.
     */
    private const LOGGED_CAP = 1000;

    /**
     * The rejections already logged in this request, keyed by component, prop and value.
     *
     * A value that renders in a loop, one per row, is reported once rather than once per row.
     * Reset after each request, before each queued job and by {@see WireKit::flush()}.
     *
     * @var array<string, true>
     */
    private static array $logged = [];

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
            // The HTTP-dev fall-through exists because a typo in one prop
            // shouldn't 500 the whole blade view.
            if (self::shouldThrowOnInvalid()) {
                throw new InvalidArgumentException($message);
            }

            self::logOnce(true, $context, $key, $value, $message.' Falling back to "'.$effectiveFallback.'".');

            return $effectiveFallback;
        }

        // Lenient (prod) — log at warning level and render the fallback.
        self::logOnce(false, $context, $key, $value, $message.' Falling back to "'.$effectiveFallback.'".');

        return $effectiveFallback;
    }

    /**
     * The same answer as enforce() for a value that has a shape rather than a list of choices: a
     * hex color, a length. There is nothing to suggest, so the message says what shape was
     * expected instead.
     *
     * Throws where the gate throws (CLI, tests, `throw_on_invalid`), logs an error and returns the
     * fallback on a strict HTTP request, and logs a warning and returns it otherwise.
     */
    public static function reject(
        string $context,
        string $key,
        string $value,
        string $expected,
        string $fallback,
    ): string {
        $message = "WireKit [{$context}]: Invalid {$key} \"".LogValue::quote($value)."\". Expected {$expected}.";

        if (self::isStrict()) {
            if (self::shouldThrowOnInvalid()) {
                throw new InvalidArgumentException($message);
            }

            self::logOnce(true, $context, $key, $value, $message.' Falling back to "'.$fallback.'".');

            return $fallback;
        }

        self::logOnce(false, $context, $key, $value, $message.' Falling back to "'.$fallback.'".');

        return $fallback;
    }

    /**
     * Log a rejection the first time this request sees its component, prop and value, at ERROR
     * on a strict request and at WARNING otherwise.
     */
    private static function logOnce(bool $asError, string $context, string $key, string $value, string $message): void
    {
        $rejection = $context."\0".$key."\0".$value;

        if (isset(self::$logged[$rejection])) {
            return;
        }

        if (count(self::$logged) >= self::LOGGED_CAP) {
            self::$logged = [];
        }

        self::$logged[$rejection] = true;

        if ($asError) {
            logger()->error($message);

            return;
        }

        logger()->warning($message);
    }

    /**
     * Forget which rejections were logged, so the next request reports its own. Called after each
     * request, before each queued job and by {@see WireKit::flush()}.
     */
    public static function forgetLogged(): void
    {
        self::$logged = [];
    }

    /**
     * Warns on unknown prop KEYS in dev.
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
                    // `acceptedPropNames()` is `@props` AND `@aware`, because Blade
                    // accepts either name on the tag and the question here is only
                    // "does this component know it?". `@aware` does not strip its
                    // key from the attribute bag, so a key written directly on the
                    // tag arrives here looking exactly like an unknown prop — and
                    // every form control in the catalog reads `announceErrors` that
                    // way. Deriving from `@props` alone reported the documented call
                    // `<x-wirekit::input announce-errors="false">` as a typo.
                    //
                    // The union was written out here and nowhere else, and then
                    // `wirekit:doctor:props` asked the same question from
                    // `extractProps()` alone and got it wrong. It has a name now, so
                    // there is one answer rather than one copy.
                    $declaredCache[$context] = ComponentRegistry::acceptedPropNames($context);
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
        // constants it reads, and only there: a copy restated here would decide
        // nothing and drift from the one that does -- see PASSTHROUGH_PREFIXES.
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
     * Scoped to the directives in SCOPE_DIRECTIVES, whose loss is not a degradation but a
     * disconnection: a caller's `x-data`, `x-init`, `x-effect`, `x-model` or `x-modelable`
     * beside the component's own is dropped, and everything written against it goes with it.
     * A duplicate `class` is merged by Blade and a duplicate `aria-*` reads as an intended
     * override; those are not this.
     *
     * Most component views set `x-data`; a handful set `x-init` and fewer still set
     * `x-modelable`. The tally itself is deliberately not written down: a count in a docblock
     * goes stale without anything noticing, and then it is a wrong promise.
     *
     * What DOES matter is a distinction the tally hid: a large minority of them set the
     * attribute only inside a condition, and for those the sentence this method logs — "your
     * scope never exists" — is simply false. `scopeDirectivesSetBy()` therefore asks whether
     * the component sets it UNCONDITIONALLY, which is the claim actually being made.
     *
     * @param  array<string, mixed>  $actual  attribute name => value
     */
    private static function warnScopeDirectiveCollisions(string $context, array $actual): void
    {
        foreach (self::discardedScopeDirectives($context, $actual) as $directive) {
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
     * The scope directives in `$actual` that this component will DISCARD. The VERDICT, with
     * no logging and no environment gate.
     *
     * Split out of `warnScopeDirectiveCollisions()` for the same reason `unknownPropNames()`
     * was split out of `warnUnknownProps()`, and the docblock there states it in a sentence
     * that applies here word for word: the warner "only logs, so nothing can fail on it, and
     * it returns early outside `app.debug` — so in production the signal does not exist at
     * all." An adopting application can therefore not assert on it, and one of them was carrying
     * a second, weaker implementation of this question over 4422 component tags: weaker
     * because it had to re-derive the occupied directives from the Blade source itself, with
     * no way to tell an unconditional `x-data` from one inside an `@if`. That distinction is
     * the whole point of `scopeDirectivesSetBy()`, and it was not available to them.
     *
     * The two share this one implementation so the rule cannot drift between the warning a
     * developer sees at runtime and the answer a test asserts on.
     *
     * @param  string  $context  the component name, as `ComponentRegistry` knows it
     * @param  array<string, mixed>  $actual  attribute name => value
     * @return list<string> the passed directives this component discards, in encounter order
     */
    public static function discardedScopeDirectives(string $context, array $actual): array
    {
        $passed = array_values(array_filter(
            array_keys($actual),
            static fn (string $name): bool => in_array($name, self::SCOPE_DIRECTIVES, true)
        ));

        if ($passed === []) {
            return [];
        }

        // The component's own template, read once per component per request. Only reached
        // when a caller actually passed one of them, so the common path costs nothing.
        static $occupiedCache = [];

        if (! array_key_exists($context, $occupiedCache)) {
            $occupiedCache[$context] = self::scopeDirectivesSetBy($context);
        }

        $occupied = $occupiedCache[$context];

        return array_values(array_filter(
            $passed,
            static fn (string $directive): bool => in_array($directive, $occupied, true)
        ));
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

        // UNCONDITIONALLY, not merely somewhere in the file. The warning built on this answer
        // tells a developer their scope "never exists" — an absolute claim, so the test behind
        // it has to be absolute too. A regex over raw Blade sees no conditions, and reported a
        // collision for every component whose `x-data` sits inside an `@if`.
        //
        // `card` is the case that surfaced it: its `x-data` is a debug warning that renders
        // only when a card is composed WRONGLY, so on a correctly composed card the attribute
        // never exists and collides with nothing. A developer whose countdown was demonstrably
        // running got told three times a day to wrap the component in their own element.
        //
        // A component that sets `x-data` both inside a condition and outside one still warns,
        // which is the point of asking about the unconditional case rather than simply exempting
        // anything conditional.
        return array_values(array_filter(
            self::SCOPE_DIRECTIVES,
            static fn (string $directive): bool => BladeParser::setsAttributeUnconditionally($source, $directive)
        ));
    }

    /**
     * Whether an attribute name belongs to a framework or platform family and is never a prop.
     *
     * Every prefix in {@see self::PASSTHROUGH_PREFIXES} names one family except `on`, which names
     * two. The HTML event handlers (`onclick`, `oninput`, `onpointerdown`) are lowercase
     * throughout and pass. The props that begin with the same two letters do not: a typo of
     * `onLabel` or `once` (`onLable`, `on-lable`, `onse`) would pass as a handler and never be
     * reported. So an `on` name counts as a handler only when it is lowercase and is not within
     * one edit of a declared prop, compared without case, since `onlabel` does not reach `onLabel`
     * either.
     *
     * @param  list<string>  $declared
     */
    public static function isPassthroughName(string $key, array $declared): bool
    {
        foreach (self::PASSTHROUGH_PREFIXES as $prefix) {
            if (! str_starts_with($key, $prefix)) {
                continue;
            }

            if ($prefix !== 'on') {
                return true;
            }

            if (preg_match('/^on[a-z]+$/', $key) !== 1) {
                return false;
            }

            foreach ($declared as $prop) {
                if (levenshtein($key, strtolower($prop)) <= 1) {
                    return false;
                }
            }

            return true;
        }

        return false;
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
        // A component with no resolvable `@props` (`glass`) yields an empty declared
        // list, and against an empty list EVERY attribute is unknown. `warnUnknownProps()`
        // has always returned early on exactly this case; the predicate did not, so each
        // caller carried its own `if ($declared === []) continue;` and one that forgot got a
        // wave of phantom findings rather than a quiet pass.
        //
        // Without the early return here every caller has to carry that guard itself, and a
        // rule that every caller must remember is a rule that one of them will not.
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
            ...self::BLADE_ATTRIBUTES,
        ];

        $unknown = [];

        foreach (array_keys($actual) as $key) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            if (in_array($key, $declared, true) || in_array($key, $reserved, true)) {
                continue;
            }
            if (self::isPassthroughName($key, $declared)) {
                continue;
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
            // rule at once, and every correct `aria-*`, `x-*` and `data-*` attribute becomes a
            // finding.
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
        $message = "WireKit [{$context}]: Invalid {$key} \"".LogValue::quote($value)."\". Allowed: {$list}.";

        $hint = SuggestSimilar::format(SuggestSimilar::byLevenshtein($value, $allowed));
        if ($hint !== null) {
            $message .= ' '.$hint;
        }

        return $message;
    }
}
