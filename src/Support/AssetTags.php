<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * The tags `@wirekitStyles`, `@wirekitScripts` and `@wirekitThemeScript` print: each points at the
 * published copy in `public/vendor/wirekit/` while that copy is current, and at the package's asset
 * route when it is missing or stale.
 *
 * The directives compile to calls on WireKitServiceProvider's methods of the same names, which
 * delegate here. Those methods stay where they are because an application's compiled views call
 * them by name until its view cache is cleared.
 *
 * @internal Called through WireKitServiceProvider; the directives are the developer-facing API.
 */
final class AssetTags
{
    /**
     * Resolve the absolute path of an asset file inside the package's dist/ directory at runtime.
     *
     * Read for (a) the current `filemtime()` behind the cache-busting query string and (b) the
     * comparison with the published copy, which falls back to the route when that copy is stale.
     *
     * Resolved from this file's own location at call time, so the path is right wherever the
     * caller sits. That matters because a directive's PHP code is compiled into a cached view under
     * `storage/framework/views/`, where that code's own `__DIR__` points somewhere else entirely.
     *
     * @param  string  $file  Filename inside dist/ (e.g. "wirekit.css")
     * @return string|null Absolute path if the file exists, null otherwise
     */
    public static function distPath(string $file): ?string
    {
        $path = \dirname(__DIR__, 2).'/dist/'.$file;

        return file_exists($path) ? $path : null;
    }

    /**
     * The URL of a dist file, with a version query: the published copy when it is current, the
     * package's asset route when that copy is missing or stale.
     *
     * Every tag this class prints decides through this one method, so the stylesheet and the
     * scripts cannot disagree about which copy is current. The staleness check exists so a
     * developer who published the assets once and then upgraded the package does not silently
     * keep serving the old file.
     */
    public static function source(string $file): string
    {
        $published = public_path('vendor/wirekit/'.$file);
        $dist = self::distPath($file);

        if (self::publishedIsStale($published, $dist)) {
            $version = $dist ? filemtime($dist) : time();

            return url('/wirekit/'.$file).'?v='.$version;
        }

        return asset('vendor/wirekit/'.$file).'?v='.filemtime($published);
    }

    /**
     * The `@wirekitThemeScript` tag: dist/wirekit-theme.js, written into the page or loaded as a file.
     *
     * One script serves both deliveries because it reads its configuration from its own tag, from
     * `data-wk-theme-storage` and `data-wk-theme-key`, instead of having values written into its
     * code. The inline form therefore puts nothing but the file's own code inside the script block,
     * and the file form loads the same code.
     *
     * The file form is a classic tag with no `defer` and no `async`, unlike the bundle tags: it has to
     * hold the page until the theme is set, which is exactly what a deferred script does not do.
     *
     * @param  string  $nonceAttr  pre-escaped ` nonce="…"` or an empty string
     */
    public static function themeScriptTag(string $nonceAttr = ''): string
    {
        $storage = config('wirekit.theme.storage', 'local') === 'cookie' ? 'cookie' : 'local';
        $attributes = $nonceAttr
            .' data-wk-theme-storage="'.$storage.'"'
            .' data-wk-theme-key="'.e((string) config('wirekit.theme.storage_key', 'wirekit-theme')).'"';

        if (config('wirekit.theme.script', 'inline') === 'external') {
            return '<script'.$attributes.' src="'.self::source('wirekit-theme.js').'"></script>'."\n";
        }

        return '<script'.$attributes.'>'.self::themeScriptSource().'</script>'."\n";
    }

    /**
     * The theme script's code without its license banner, read once per process.
     *
     * The banner is the only comment in the built file, and a page does not need a license notice
     * written into it.
     */
    public static function themeScriptSource(): string
    {
        static $source = null;

        if ($source === null) {
            $path = self::distPath('wirekit-theme.js');

            if ($path === null) {
                // Said out loud: an empty script block renders cleanly and sets no theme at all.
                logger()->warning('WireKit: dist/wirekit-theme.js is missing, so @wirekitThemeScript renders an empty script. Reinstall the package.');
            }

            $code = $path === null ? '' : (string) file_get_contents($path);
            $source = trim((string) preg_replace('#\A/\*!.*?\*/#s', '', $code));
        }

        return $source;
    }

    /**
     * Build one `<script>` tag for a bundle, published copy or package route.
     *
     * The `@wirekitScripts` directive prints one for the configured bundle and, when the
     * ApexCharts adapter is switched on, a second one for it; both decide through source().
     *
     * @param  string  $file  bundle filename, e.g. `wirekit.js`
     * @param  string  $nonceAttr  pre-escaped ` nonce="…"` or an empty string
     * @param  string  $extraAttrs  pre-escaped extra attributes, each with its own leading space
     */
    public static function scriptTag(string $file, string $nonceAttr = '', string $extraAttrs = ''): string
    {
        $src = self::source($file);

        // `data-navigate-once` — the bundle registers Alpine components and document
        // listeners, and that is a once-per-DOCUMENT job. Livewire's navigate re-executes a
        // head script on every client-side hop unless the attribute is there, as its own
        // bundle's tag has it. Without it the bundle runs once more per hop, and the
        // listener set grows for as long as somebody navigates without a full reload,
        // which in a console shell is the normal way to use the app.
        //
        // Counting <script> tags does not see it — the old tag is replaced rather than added,
        // so the DOM keeps showing exactly one; `performance.getEntriesByType('resource')`
        // across two real hops does.
        //
        // Unconditional rather than gated on Livewire being installed. It is an inert data
        // attribute to anything that does not read it, and a detection branch here would be
        // a second thing to keep right for no gain.
        return '<script'.$nonceAttr.$extraAttrs.' data-navigate-once="true" src="'.$src.'" defer></script>'."\n";
    }

    /**
     * The translated accessible name for the overlay landmark, as an HTML attribute.
     *
     * Every teleported panel lives inside one landmark, and a landmark exists to be
     * announced — so its name is read by exactly the people who cannot see that the
     * region is empty of meaning otherwise. A literal in the bundle would make an
     * application shipping eight languages announce one English word in eight otherwise
     * translated region lists.
     *
     * The name rides the bundle's own `<script>` tag rather than an inline script or a
     * `<div>` of its own, and both alternatives are worse: an inline script is a second
     * thing a strict Content-Security-Policy must nonce and a second thing that can be
     * blocked, and emitted markup lands wherever the developer put `@wirekitScripts` —
     * inside an `overflow: hidden` wrapper it would be clipped,
     * which is the exact problem teleporting out of the tree exists to solve. The tag is
     * already present, already nonced, and already re-rendered by the server on every
     * page a `wire:navigate` arrives at.
     *
     * Falls back to the English literal in the bundle when the catalog has no entry: a
     * landmark with no accessible name is worse than one named in the wrong language.
     */
    public static function overlayLabelAttribute(): string
    {
        return ' data-wk-overlay-label="'.e(__('wirekit::Overlays')).'"';
    }

    /**
     * The stylesheet tag, decided by the same rule as scriptTag().
     *
     * @param  string  $nonceAttr  pre-escaped ` nonce="…"` or an empty string
     */
    public static function styleTag(string $file, string $nonceAttr = ''): string
    {
        $tag = '<link rel="stylesheet"'.$nonceAttr.' href="'.self::source($file).'">'."\n";

        // While compact classes are on, the kit's stylesheet brings the sheet that declares them,
        // decided here at render time: a layout compiled before the switch existed calls this
        // method with `wirekit.min.css` and links it too.
        if ($file === 'wirekit.min.css' && CompactClasses::enabled()) {
            $tag .= '<link rel="stylesheet"'.$nonceAttr.' href="'.self::source('wirekit-compact.css').'">'."\n";
        }

        return $tag;
    }

    /**
     * Is the published copy something other than what this package ships?
     *
     * Compared by content, not by modification time. A modification time answers "which
     * file was written later", and that is not the question: Composer writes an upgraded
     * `dist/` with whatever timestamp the archive or the filesystem hands it, and a
     * `vendor:publish` from the previous version can carry a later one. A time comparison
     * would then call the published copy current and serve the old bytes, with a
     * fresh-looking `?v=` stamp and no error anywhere.
     *
     * A missing published file is stale by definition. Beyond that: sizes first, because two
     * files of different length cannot be equal and the check costs one stat; a hash only when
     * the sizes agree, which is the one case where bytes still have to be read.
     *
     * Memoized per process, keyed by the published file's own mtime and size, so the answer
     * self-invalidates the moment anything republishes — rather than by path alone, which
     * would cache a verdict across a publish inside a long-lived worker.
     */
    public static function publishedIsStale(string $published, ?string $dist): bool
    {
        if (! file_exists($published)) {
            return true;
        }

        // Nothing to compare against. The published copy is all there is, so serve it —
        // returning "stale" here would route to a file the package does not have.
        if ($dist === null || ! is_file($dist)) {
            return false;
        }

        static $memo = [];

        $publishedSize = filesize($published);
        $key = $published.'|'.filemtime($published).'|'.$publishedSize.'|'.$dist;

        if (isset($memo[$key])) {
            return $memo[$key];
        }

        $distSize = filesize($dist);

        return $memo[$key] = $publishedSize !== $distSize
            || hash_file('xxh128', $published) !== hash_file('xxh128', $dist);
    }
}
