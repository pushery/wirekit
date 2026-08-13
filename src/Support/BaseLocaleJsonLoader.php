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
     * Decoded base-language catalogs, keyed by language subtag.
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
    public function __construct(
        private readonly Loader $loader,
        private readonly string $path,
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

        $base = $this->baseLanguage((string) $locale);

        if ($base === null) {
            return $lines;
        }

        $catalog = $this->catalog($base);

        if ($catalog === []) {
            return $lines;
        }

        // Base UNDERNEATH: `array_merge` lets the right-hand side win per key,
        // and `$lines` holds everything the real loader found — this package's
        // own exact-locale file should one ever ship, and the application's
        // `lang/{locale}.json`, which has to keep overriding us key by key.
        // The same function Laravel's own `loadJsonPaths()` uses to stack its
        // paths, so the key semantics here are the framework's rather than a
        // second set that could disagree with it.
        return array_merge($catalog, $lines);
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
     * The decoded catalog for a base language, or an empty array when this
     * package ships none for it.
     *
     * @return array<string, string>
     */
    private function catalog(string $language): array
    {
        if (array_key_exists($language, $this->catalogs)) {
            return $this->catalogs[$language];
        }

        $file = $this->path.'/'.$language.'.json';

        if (! is_file($file)) {
            return $this->catalogs[$language] = [];
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
        return $this->catalogs[$language] = is_array($decoded) ? $decoded : [];
    }
}
