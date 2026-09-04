<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Contracts\Translation\Loader;

/**
 * Makes a regional locale fall back to its base-language JSON catalog.
 *
 * Laravel's JSON translation channel matches the locale filename EXACTLY: the
 * loader reads `{$path}/{$locale}.json`, and nothing anywhere between the
 * application's locale and that filename derives a base language from a
 * regional tag. `fallback_locale` does not cover the gap either — the fallback
 * loop in `Translator::get()` runs only AFTER the JSON lookup has already
 * missed, and it walks the PHP-group path (`{$path}/{$locale}/{$group}.php`),
 * never the JSON one.
 *
 * The consequence is larger than it sounds. An application whose locale is
 * `pt-PT` looks for a `pt-PT.json` that nobody ships, finds nothing, and
 * renders English inside a Portuguese page — while a complete `pt.json` sits
 * in this package unread. Not part of the catalog: all of it, for every
 * regional variant of every language shipped. `de-AT`, `de-CH`, `es-MX`,
 * `fr-CA`, `pt-BR` and the rest all land the same way, and nothing fails
 * loudly enough to be noticed, because falling back to English is exactly what
 * an untranslated key is supposed to look like.
 *
 * This decorator sits in front of the real loader, notices the JSON channel,
 * and merges the base-language catalog UNDERNEATH whatever the real loader
 * found. Underneath is the whole design: the developer's own
 * `lang/{locale}.json` is in that result and must keep winning per key, so a
 * merge in the other direction would silently overwrite a translation someone
 * wrote on purpose.
 *
 * SCOPE — deliberately narrow. It reads one directory, the one this package
 * ships its own catalogs in, and it is blind to every other registered
 * translation path. Whether an application's own `lang/pt.json` ought to
 * answer for `pt-PT` is that application's decision; a UI library taking it on
 * their behalf would change how their catalogs resolve without being asked.
 */
final class BaseLocaleJsonLoader implements Loader
{
    /**
     * Decoded catalogs, keyed by the filename stem they were read from — a
     * language subtag (`pt`) or a full regional tag (`pt-BR`).
     *
     * `Translator::load()` already caches per locale, so a single request
     * normally decodes at most one file. The memo covers the case where
     * several regional variants of one language resolve in the same process —
     * a queue worker rendering one mail per recipient locale, a language
     * switcher — where the same catalog would otherwise be re-read per variant.
     *
     * @var array<string, array<string, string>>
     */
    private array $catalogs = [];

    /**
     * @param  Loader  $loader  The loader being decorated; every call not handled here goes to it.
     * @param  string  $path  Directory holding this package's own `{language}.json` catalogs.
     */
    /**
     * The prefix every key this package ships carries.
     *
     * Laravel's JSON channel has NO namespace of its own: `FileLoader::load()`
     * only reaches `loadJsonPaths()` when group and namespace are both `*`, so
     * `addNamespace()` never touches it. What DOES work is that
     * `Translator::get()` looks the raw key up as a literal array key in the
     * merged flat catalog before it ever calls `parseKey()` — so a catalog
     * holding the literal key `wirekit::Close` answers `__('wirekit::Close')`.
     *
     * This is therefore a key-PREFIX convention inside the one flat namespace,
     * not a framework namespace, and the distinction matters: the prefix is
     * part of the key, so it must appear in the shipped JSON exactly as it
     * appears at the call site.
     */
    public const NAMESPACE = 'wirekit::';

    /**
     * @param  Loader  $loader  The loader being decorated; every call not handled here goes to it.
     * @param  string  $path  Directory holding this package's own `{language}.json` catalogs.
     * @param  bool  $legacyKeyBridge  Whether an application's pre-namespace override of a plain key still applies.
     */
    public function __construct(
        private readonly Loader $loader,
        private readonly string $path,
        private readonly bool $legacyKeyBridge = true,
    ) {}

    /**
     * The parameters are untyped because the contract's are: PHP allows a
     * return type to be added where the interface declares none, but narrowing
     * an untyped parameter to `string` is a fatal incompatibility. The types
     * live in the docblock instead, exactly as they do on Laravel's own
     * FileLoader.
     *
     * @param  string  $locale
     * @param  string  $group
     * @param  string|null  $namespace
     * @return array<string, mixed>
     */
    public function load($locale, $group, $namespace = null): array
    {
        $lines = $this->loader->load($locale, $group, $namespace);

        // The JSON channel only, which Laravel signals with this `*` / `*`
        // pair. The PHP-group channel is untouched on purpose — it already has
        // a working `fallback_locale` path, and a second fallback layered on
        // top of a working one is a source of surprises, not of translations.
        if ($group !== '*' || $namespace !== '*') {
            return $lines;
        }

        // THE ENGLISH BACKSTOP, and it is unconditional on purpose.
        //
        // Before the keys carried a prefix this layer did not need to exist: the key WAS the
        // English text, so a locale nobody ships a catalog for rendered correct English by
        // falling through to the key itself. `lang/en.json` was a 252-entry identity map, which
        // is exactly what that property looks like written down.
        //
        // The prefix destroys it. An unresolved `wirekit::Close` paints the literal string
        // `wirekit::Close` onto the page — a new class of visible failure, and one no test
        // notices unless it asks for a locale with no catalog at all. So the English catalog is
        // merged underneath EVERY locale, not only the regional ones, and `lang/en.json` stops
        // being an identity map and becomes the backstop it now has to be.
        $english = $this->catalog('en');

        $base = $this->baseLanguage((string) $locale);

        $catalog = $base === null ? [] : $this->catalog($base);

        // This package's catalog for the locale itself, under its canonical
        // dash spelling. It covers two jobs that used to be one.
        //
        // The REGIONAL one: `pt_BR` and `pt-BR` are one locale, but the real
        // loader interpolates the locale into a filename verbatim, so the
        // underscore form never reaches the `pt-BR.json` shipped here. It
        // misses, the base merge above supplies `pt.json`, and the reader gets
        // fluent, complete, EUROPEAN Portuguese. No key is missing and nothing
        // throws — it is simply the wrong variety, which is the outcome
        // shipping a regional catalog was meant to end. The underscore spelling
        // is not exotic: PHP's own locale primitives emit it, and it is a
        // common `config/app.php` value.
        //
        // The BRIDGE one, and this is why it is no longer conditional on the
        // spelling having changed. `bridgeLegacyKeys()` below has to tell OUR
        // entry for a key from the application's, and it does that by comparing
        // against what this package ships. For `de` the real loader has already
        // read `de.json` into `$lines`, so leaving it out of `$ours` left that
        // comparison holding the ENGLISH backstop value — which never equals
        // the German one, so every key looked like the application's and the
        // bridge skipped all of them. Measured: an application's legacy `Close`
        // override applied in `en` and in nothing else, i.e. the bridge was
        // inert for all six locales that ship a complete catalog, which is
        // every locale it exists to serve.
        $own = $this->ownCatalog((string) $locale);

        if ($english === [] && $catalog === [] && $own === []) {
            return $lines;
        }

        // Base UNDERNEATH: `array_merge` lets the right-hand side win per key,
        // and `$lines` holds everything the real loader found — this package's
        // own exact-locale file should one ever ship, and the application's
        // `lang/{locale}.json`, which has to keep overriding us key by key.
        // The same function Laravel's own `loadJsonPaths()` uses to stack its
        // paths, so the key semantics here are the framework's rather than a
        // second set that could disagree with it.
        //
        // Four layers now, and the order is the whole design: English at the bottom as the
        // backstop, then the base language, then this package's own catalog for the locale
        // itself over it, then everything the real loader found — which is the same
        // exact-locale file plus the application's own `lang/{locale}.json`, and that one has
        // to keep winning key by key.
        $ours = array_merge($english, $catalog, $own);

        // ⚠️ THE UN-PREFIXED COPY, AT THE VERY BOTTOM, AND IT IS A COMPATIBILITY LAYER RATHER
        // THAN A LEFTOVER.
        //
        // Registering this package's lang directory as a JSON path had a side effect nobody
        // designed: its keys were plain English words, so an APPLICATION calling `__('Close')`
        // in its own template got a translation it never wrote. Prefixing every key would end
        // that silently — the text simply reverts to English, no error, no failing test, and
        // the reader notices before anyone else does. That is the same shape of failure this
        // whole change exists to fix, and shipping it as the fix would have been the worst
        // available answer.
        //
        // So the catalog is offered BOTH ways: `wirekit::Close` for the components, and `Close`
        // for whoever was already relying on it. The plain copy sits UNDERNEATH `$lines`, so an
        // application's own entry still wins over it exactly as before — this adds a fallback,
        // it never takes a decision away.
        //
        // And it does NOT re-open the defect. The components no longer ask for `Close`; they
        // ask for `wirekit::Close`, which an application's plain `Map`/`Read`/`Dismiss` cannot
        // reach. The plain layer answers only lookups the application makes on its own behalf.
        $plain = [];

        foreach ($ours as $key => $value) {
            $plain[substr($key, strlen(self::NAMESPACE))] = $value;
        }

        $merged = array_merge($plain, $ours, $lines);

        return $this->bridgeLegacyKeys($ours, $merged, $lines);
    }

    /**
     * Let an application's PRE-NAMESPACE override of a plain key keep applying.
     *
     * Before this package prefixed its keys, an application translated `Close` by writing
     * `"Close"` in its own `lang/{locale}.json`, and that entry outranked ours because the real
     * loader's result is merged last. After the rename our key is `wirekit::Close`, which their
     * catalog says nothing about — so without this step every existing override would stop
     * working in the release that renamed the keys, silently, with no test going red and no
     * error anywhere. That is precisely the failure mode the rename was reported for, and
     * shipping it as the fix would have been the worst possible answer to it.
     *
     * ⚠️ THE PRICE IS NAMED RATHER THAN HIDDEN: for an application that has the collision, the
     * bridge PRESERVES it. If their `Map` means "to map" and ours means "a map", the bridge
     * still hands their wording to our component. That application has to act — rename their
     * key, adopt `wirekit::Map`, or turn the bridge off. What the change buys them is that
     * `wirekit:verify` can now NAME the collision, and that adopting the namespaced key is a
     * way out that did not exist before.
     *
     * @param  array<string, string>  $ours  This package's own catalog for this locale, namespaced.
     * @param  array<string, mixed>  $merged  The stacked result the bridge writes into.
     * @param  array<string, mixed>  $lines  What the real loader found — the application's catalog is in here.
     * @return array<string, mixed>
     */
    private function bridgeLegacyKeys(array $ours, array $merged, array $lines): array
    {
        if (! $this->legacyKeyBridge) {
            return $merged;
        }

        foreach ($ours as $key => $ourValue) {
            $plain = substr($key, strlen(self::NAMESPACE));

            // Nothing to bridge: the application never translated this string.
            if (! array_key_exists($plain, $lines)) {
                continue;
            }

            // The application has ADOPTED the namespaced key, and an explicit choice outranks
            // an inferred one. Told apart from OUR OWN entry — the real loader reads this
            // package's `{locale}.json` too, so `$lines` carries our namespaced keys as well —
            // by comparing against what we ship: equal means it is ours, different means theirs.
            if (array_key_exists($key, $lines) && $lines[$key] !== $ourValue) {
                continue;
            }

            $merged[$key] = $lines[$plain];
        }

        return $merged;
    }

    /**
     * @param  string  $namespace
     * @param  string  $hint
     */
    public function addNamespace($namespace, $hint): void
    {
        $this->loader->addNamespace($namespace, $hint);
    }

    /**
     * @param  string  $path
     */
    public function addJsonPath($path): void
    {
        $this->loader->addJsonPath($path);
    }

    /**
     * @return array<string, string>
     */
    public function namespaces(): array
    {
        return $this->loader->namespaces();
    }

    /**
     * Forward everything the Loader CONTRACT does not declare.
     *
     * The interface names four methods. The FileLoader every Laravel
     * application actually runs also exposes `paths()`, `jsonPaths()` and
     * `addPath()` — and those get called: by this package's own suite, and by
     * any application or package that registers a translation path of its own.
     * A decorator implementing the contract alone would be perfectly
     * type-correct and would break every one of them, which is precisely the
     * failure a wrapper exists to prevent.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->loader->{$method}(...$arguments);
    }

    /**
     * This package's OWN catalog for the locale, under its canonical dash
     * spelling — `de` for `de`, `pt-BR` for both `pt-BR` and `pt_BR`.
     *
     * It is read even when the real loader has already read the same file. That
     * looks redundant on the resolution path and is not: `$lines` cannot answer
     * "what does this package ship for this key?", because the application's
     * catalog is merged into it and is indistinguishable there. The bridge needs
     * that answer, so it has to come from the file this package owns.
     *
     * @return array<string, string>
     */
    private function ownCatalog(string $locale): array
    {
        $normalized = str_replace('_', '-', $locale);

        // The whole tag is checked, not only its first subtag: this string is
        // about to be interpolated into a filename, and the locale it came from
        // is settable at runtime. `baseLanguage()` below validates the base for
        // the same reason and is not sufficient here, because the part after
        // the dash is the part that would carry a traversal. The trailing group
        // repeats zero or more times, so a plain language tag passes the same
        // check rather than needing a second one.
        if (preg_match('/^[A-Za-z]{2,8}(-[A-Za-z0-9]{2,8})*$/', $normalized) !== 1) {
            return [];
        }

        return $this->catalog($normalized);
    }

    /**
     * The base language subtag of a regional locale, or null when there is
     * nothing to fall back to.
     */
    private function baseLanguage(string $locale): ?string
    {
        // `pt_BR` and `pt-BR` are one locale wearing two separators, and both
        // arrive here — Laravel normalizes neither, and PHP's own locale
        // primitives emit the underscore form. Splitting on the dash alone
        // would leave half the affected applications exactly where they were.
        $normalized = str_replace('_', '-', $locale);
        $base = explode('-', $normalized, 2)[0];

        // A locale that IS its own base — `pt`, `de`, `en` — has nothing to
        // fall back to, so this whole class is a strict no-op for it. That is
        // the common case, and it costs one string comparison.
        if ($base === $normalized) {
            return null;
        }

        // The subtag is about to become a filename, and the locale it came
        // from is settable at runtime (a URL segment, an Accept-Language
        // header, a stored user preference), so it is checked against the
        // shape a language subtag actually has rather than trusted. Laravel
        // interpolates the locale into a path without this check, so nothing
        // here closes a hole that is open only in this class — it declines to
        // open a second one.
        //
        // Verbatim, not lower-cased: the framework treats the exact locale as
        // a case-sensitive filename, and resolving `PT-BR` through `pt.json`
        // while `PT` itself resolves to nothing would be an asymmetry a
        // developer has no way to predict.
        return preg_match('/^[A-Za-z]{2,8}$/', $base) === 1 ? $base : null;
    }

    /**
     * The decoded catalog for a filename stem — a base language (`pt`) or a
     * full regional tag (`pt-BR`) — or an empty array when this package ships
     * none for it.
     *
     * Both callers validate the stem's shape before it gets here, and neither
     * may stop: the value originates in a runtime-settable locale, and this is
     * the line that turns it into a path.
     *
     * @return array<string, string>
     */
    private function catalog(string $stem): array
    {
        if (array_key_exists($stem, $this->catalogs)) {
            return $this->catalogs[$stem];
        }

        $file = $this->path.'/'.$stem.'.json';

        if (! is_file($file)) {
            return $this->catalogs[$stem] = [];
        }

        $decoded = json_decode((string) file_get_contents($file), true);

        // A malformed catalog degrades to "no fallback" instead of throwing.
        // Laravel's own loader throws on invalid JSON, and that is right for a
        // file it was explicitly asked to read; this one nobody asked for.
        // Throwing here would take down a page for a locale that, without this
        // class, would have rendered English and kept working — a decorator
        // must not invent a failure the undecorated request did not have. The
        // shipped catalogs are pinned as valid JSON by the suite, so this is
        // the shape of the guarantee rather than a tolerance for a broken file.
        return $this->catalogs[$stem] = is_array($decoded) ? $decoded : [];
    }
}
