<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * The flag artwork: where it is, what it holds, and the URL a flag is served from.
 *
 * `resources/flags/` carries every flag in two formats, together with a manifest, `flags.json`,
 * that names each one and the sha256 of each file. Everything here reads that manifest rather
 * than the directory, so a file the manifest does not name is never served, and no URL is built
 * for a flag that does not exist.
 *
 * ⚠️ THE ARTWORK USED TO LIVE IN A SEPARATE PACKAGE, and this class is named after it. Owner
 * decision 2026-09-18: it belongs here, for the reason the fonts are already here — 115 font
 * files and 5.82 MB ship unconditionally and are only LOADED when `<x-wirekit::fonts />` asks
 * for them. The flags are 1.43 MB against that, which is 16% of the package and 1.5% of a
 * typical application's vendor directory, and a second repository would have cost a CI lane, a
 * release cycle, a Packagist registration and a hand-kept version pairing for it.
 *
 * The NAME stays because the country-picker recipe calls `FlagPackage::isoCodes()` in published
 * documentation. Renaming it is a breaking change, and a breaking change needs a major version.
 *
 * Where the artwork is:
 *
 *   1. `wirekit.flags.path`, when it is set: a copy elsewhere, such as a test fixture. A relative
 *      path is resolved against the application's base path.
 *   2. Otherwise, `resources/flags/` inside this package.
 */
final class FlagPackage
{
    /** The two artworks the package carries. */
    public const FORMATS = ['4x3', '1x1'];

    /** @var array{stamp: string, manifest: array<array-key, mixed>}|null */
    private static ?array $loaded = null;

    /** @var array{stamp: string, current: bool}|null */
    private static ?array $publishedVerdict = null;

    private static bool $reportedMissing = false;

    /** @var array<string, true> */
    private static array $reportedUnknown = [];

    /** The package's root directory, or null when it is not installed or holds no manifest. */
    public static function root(): ?string
    {
        $configured = config('wirekit.flags.path');

        if (is_string($configured) && $configured !== '') {
            $path = str_starts_with($configured, '/') ? $configured : base_path($configured);
        } else {
            $path = __DIR__.'/../../resources/flags';
        }

        $real = realpath($path);

        return $real !== false && is_file($real.'/flags.json') ? $real : null;
    }

    /**
     * The decoded manifest, read once per process and again only when the file changes.
     *
     * @return array<array-key, mixed>|null
     */
    public static function manifest(): ?array
    {
        $root = self::root();

        if ($root === null) {
            return null;
        }

        $file = $root.'/flags.json';
        $stamp = $file.'|'.(string) filemtime($file);

        if (self::$loaded !== null && self::$loaded['stamp'] === $stamp) {
            return self::$loaded['manifest'];
        }

        $manifest = json_decode((string) file_get_contents($file), true);

        if (! is_array($manifest) || ! is_array($manifest['flags'] ?? null)) {
            return null;
        }

        self::$loaded = ['stamp' => $stamp, 'manifest' => $manifest];

        return $manifest;
    }

    /** The sha256 the manifest records for one flag file, or null when the package has no such file. */
    public static function checksum(string $code, string $format): ?string
    {
        $flags = self::manifest()['flags'] ?? null;
        $entry = is_array($flags) ? ($flags[$code] ?? null) : null;
        $checksums = is_array($entry) ? ($entry['sha256'] ?? null) : null;
        $checksum = is_array($checksums) ? ($checksums[$format] ?? null) : null;

        return is_string($checksum) && preg_match('/^[0-9a-f]{64}$/', $checksum) === 1 ? $checksum : null;
    }

    /**
     * The file behind a flag, or null when the manifest does not name it.
     *
     * The manifest decides which names exist. The path check after it only confirms that the file
     * sits where the manifest says, inside the package, and is not a link out of it.
     */
    public static function file(string $code, string $format): ?string
    {
        if (! in_array($format, self::FORMATS, true) || self::checksum($code, $format) === null) {
            return null;
        }

        $root = self::root();

        if ($root === null) {
            return null;
        }

        // `root()` IS the flags directory — it is the path that holds `flags.json`, and it
        // returns null when that file is not directly inside it. So the format folder sits one
        // level down from `$root`, not two: appending `flags/` here asked for
        // `<root>/flags/4x3/de.svg` and the artwork lives at `<root>/4x3/de.svg`.
        //
        // The lookup missed by exactly one segment, `realpath()` returned false, and the route
        // turned that into a 404 for every code in every install that had not published the
        // artwork. `manifest()` and `isoCodes()` read `$root.'/flags.json'` and were right all
        // along, which is why the page rendered and only the image bytes were missing.
        $file = realpath($root.'/'.$format.'/'.$code.'.svg');

        // Containment is asserted against `$root` itself for the same reason. This is narrower
        // than the old `$root.'/flags/'`, not wider: a code that climbs out of the flags
        // directory still fails it, and FORMATS above already pins the middle segment.
        return $file !== false && str_starts_with($file, $root.'/') && is_file($file) ? $file : null;
    }

    /**
     * The URL a flag is served from, or null when the package has no such flag.
     *
     * The key is the file's own checksum, so the one-year immutable cache ends exactly when this
     * file changes and not when anything else in the package does. The web server's copy is used
     * when a publish of the installed package is in place; otherwise the package route serves it.
     */
    public static function url(string $code, string $format): ?string
    {
        $checksum = self::checksum($code, $format);

        if ($checksum === null) {
            return null;
        }

        $key = substr($checksum, 0, 16);

        if (self::publishedCopyIsCurrent()) {
            return asset('vendor/wirekit/flags/'.$format.'/'.$code.'.svg').'?v='.$key;
        }

        return url('/wirekit/flags/'.$format.'/'.$code.'.svg').'?v='.$key;
    }

    /**
     * Whether `vendor:publish --tag=wirekit-flags` left a copy that matches the installed package.
     *
     * Compared by the manifest's content, never by timestamps: a publish from an earlier release
     * can carry a later mtime than the package that replaced it, and a comparison by time would
     * then serve the old artwork under a fresh-looking key. The verdict is kept until either file
     * changes, so a page listing every flag hashes the manifests once rather than once per flag.
     */
    public static function publishedCopyIsCurrent(): bool
    {
        $root = self::root();
        $published = public_path('vendor/wirekit/flags/flags.json');

        if ($root === null || ! is_file($published)) {
            return false;
        }

        $stamp = implode('|', [$root, (string) filemtime($root.'/flags.json'), (string) filemtime($published), (string) filesize($published)]);

        if (self::$publishedVerdict !== null && self::$publishedVerdict['stamp'] === $stamp) {
            return self::$publishedVerdict['current'];
        }

        $current = hash_file('sha256', $published) === hash_file('sha256', $root.'/flags.json');
        self::$publishedVerdict = ['stamp' => $stamp, 'current' => $current];

        return $current;
    }

    /**
     * The ISO 3166-1 country codes the installed package carries, in the manifest's order, or an
     * empty list without the package. A country picker lists these rather than every region its
     * locale data knows, because a region without a flag would only draw a placeholder.
     *
     * @return list<string>
     */
    public static function isoCodes(): array
    {
        $flags = self::manifest()['flags'] ?? null;

        if (! is_array($flags)) {
            return [];
        }

        $codes = [];

        foreach ($flags as $code => $entry) {
            if (is_string($code) && is_array($entry) && ($entry['iso'] ?? false) === true) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /**
     * Resolve the flags in a `combobox` or `multi-select` option payload.
     *
     * OptionMedia leaves an option with `media: 'flag'` and its code. Here the code becomes the
     * keyed URL, under `src`, which the template's flag branch reads. An option whose flag cannot be
     * resolved keeps no `src`, the template draws an empty box of the same size, and the reason is
     * logged the way the flag component logs it. The code itself leaves the payload, because the
     * browser has no use for it.
     *
     * @param  list<array<string, mixed>>  $options
     * @return list<array<string, mixed>>
     */
    public static function attach(array $options): array
    {
        $resolved = [];

        foreach ($options as $option) {
            if (($option['media'] ?? null) === 'flag' && is_string($option['flag'] ?? null)) {
                $code = $option['flag'];
                unset($option['flag']);

                if (self::manifest() === null) {
                    self::reportMissingOnce();
                } else {
                    $url = self::url($code, '4x3');

                    if ($url !== null) {
                        $option['src'] = $url;
                    } else {
                        self::reportUnknownOnce($code);
                    }
                }
            }

            $resolved[] = $option;
        }

        return $resolved;
    }

    /**
     * Log, once per process, that the flag artwork could not be found where it should be.
     *
     * ⚠️ This used to mean "the optional package is not installed", which was the ONLY way it
     * could fire. The artwork now ships with WireKit, so a missing root means one thing instead:
     * `wirekit.flags.path` points somewhere that holds no `flags.json`. That is a configuration
     * mistake rather than a missing dependency, and the message says so — telling somebody to
     * install a package that no longer exists is worse than saying nothing.
     *
     * Guarded the way the icon system guards its own degradation log: this renders in contexts
     * with no container behind it, and a diagnostic that throws is worse than silence.
     */
    public static function reportMissingOnce(): bool
    {
        if (self::$reportedMissing) {
            return false;
        }

        self::$reportedMissing = true;

        if (function_exists('logger')) {
            logger()->warning('WireKit: a flag was drawn as a placeholder because no flags.json was found. The artwork ships with WireKit, so check `wirekit.flags.path` — it is set and points somewhere that holds none.');
        }

        return true;
    }

    /**
     * Log, once per code and only in debug mode, that the package has no flag for a code.
     *
     * Silent in production on purpose: a country code often comes from data, and one stale record
     * must not fill a production log.
     */
    public static function reportUnknownOnce(string $code): bool
    {
        if (isset(self::$reportedUnknown[$code]) || ! config('app.debug')) {
            return false;
        }

        self::$reportedUnknown[$code] = true;

        if (function_exists('logger')) {
            logger()->warning(sprintf('WireKit: there is no flag for "%s", so a placeholder was drawn. resources/flags/flags.json lists the codes it carries.', $code));
        }

        return true;
    }

    /** Forget what was loaded and reported. For tests only. */
    public static function flush(): void
    {
        self::$loaded = null;
        self::$publishedVerdict = null;
        self::$reportedMissing = false;
        self::$reportedUnknown = [];
    }
}
