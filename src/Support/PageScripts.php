<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Support\Js;

/**
 * The scripts of a page under `'scripts' => ['bundle' => 'auto']`: the shared runtime and the
 * factories the page's markup starts, from the per-page bundle in `dist/auto/`.
 *
 * `@wirekitScripts` writes a region that holds the full bundle's tag, so a page works as it does on
 * `full` wherever nothing rewrites it. `Http\WritePageScripts` rewrites the region of an HTML
 * response: every name in an `x-data` that starts with `wirekit` and has a module in the bundle's
 * manifest, from the kit's components and from a page's own markup alike, becomes an import of
 * that module, with a preload of each file the modules will ask for.
 *
 * @internal Called by `@wirekitScripts` and `Http\WritePageScripts`; not a developer-facing API.
 */
final class PageScripts
{
    public const START = '<!--wirekit:page-scripts-->';

    public const END = '<!--/wirekit:page-scripts-->';

    /** @var array{build: string, runtime: array{file: string, preload: list<string>}, factories: array<string, array{file: string, preload: list<string>}>}|false|null */
    private static array|false|null $manifest = null;

    /**
     * The region `@wirekitScripts` writes: the full bundle's tag between the two markers.
     */
    public static function region(string $fallback): string
    {
        return self::START.$fallback.self::END;
    }

    /**
     * The page with its region rewritten to the runtime and the factories it starts, or the page
     * as it came where it has no region or the bundle carries no manifest.
     */
    public static function rewrite(string $html): string
    {
        $start = strpos($html, self::START);
        $end = $start === false ? false : strpos($html, self::END, $start);
        $manifest = self::manifest();

        if ($start === false || $end === false || $manifest === false) {
            return $html;
        }

        $fallback = substr($html, $start + strlen(self::START), $end - $start - strlen(self::START));
        $tags = self::tags(self::factoriesIn($html, $manifest), $manifest, self::attributesOf($fallback));

        return substr($html, 0, $start).$tags.substr($html, $end + strlen(self::END));
    }

    /**
     * The factory names a page starts that the bundle has a module for, sorted.
     *
     * @param  array{factories: array<string, array{file: string, preload: list<string>}>}  $manifest
     * @return list<string>
     */
    public static function factoriesIn(string $html, array $manifest): array
    {
        preg_match_all('/\bx-data\s*=\s*(["\'])\s*(wirekit[A-Z]\w*)/', $html, $matches);

        $names = array_values(array_unique(array_filter(
            $matches[2],
            static fn (string $name): bool => isset($manifest['factories'][$name]),
        )));
        sort($names);

        return $names;
    }

    /**
     * The nonce and the overlay root's label of the fallback tag, which the module script carries
     * on in its place.
     */
    private static function attributesOf(string $fallback): string
    {
        $attributes = '';

        // `data-wk-overlay-label` is the overlay root's name (AssetTags::overlayLabelAttribute()),
        // which the runtime reads from whichever script carries it.
        foreach (['nonce', 'data-wk-overlay-label'] as $name) {
            if (preg_match('/\s'.preg_quote($name, '/').'="([^"]*)"/', $fallback, $match) === 1) {
                $attributes .= ' '.$name.'="'.$match[1].'"';
            }
        }

        return $attributes;
    }

    /**
     * @param  list<string>  $factories
     * @param  array{build: string, runtime: array{file: string, preload: list<string>}, factories: array<string, array{file: string, preload: list<string>}>}  $manifest
     */
    private static function tags(array $factories, array $manifest, string $attributes): string
    {
        $published = self::published();
        // The build's fingerprint, the same on every server that deploys the same build.
        $version = $manifest['build'];
        $nonce = preg_match('/ nonce="[^"]*"/', $attributes, $match) === 1 ? $match[0] : '';

        $entries = [$manifest['runtime']['file']];
        $preload = $manifest['runtime']['preload'];

        foreach ($factories as $name) {
            $entries[] = $manifest['factories'][$name]['file'];
            $preload = [...$preload, ...$manifest['factories'][$name]['preload']];
        }

        $links = '';

        foreach (array_values(array_unique([...$entries, ...$preload])) as $file) {
            $links .= '<link rel="modulepreload"'.$nonce.' href="'.e(self::url($published, $file, $version)).'">'."\n";
        }

        $imports = 'import { register } from '.self::literal(self::url($published, $manifest['runtime']['file'], $version)).';';
        $handed = [];

        foreach ($factories as $index => $name) {
            $imports .= ' import f'.$index.' from '.self::literal(self::url($published, $manifest['factories'][$name]['file'], $version)).';';
            $handed[] = $name.': f'.$index;
        }

        return $links.'<script type="module"'.$attributes.' data-navigate-once="true">'.$imports.' register({ '.implode(', ', $handed).' });</script>'."\n";
    }

    /**
     * A URL inside a module script, as a JavaScript string the HTML parser cannot end early: `Js`
     * writes `<` as an escape, so no `</script>` can appear in it.
     */
    private static function literal(string $url): string
    {
        return (string) Js::from($url);
    }

    /**
     * The URL of one file of the bundle. The runtime and a factory keep their names from build to
     * build and take the build's fingerprint. A chunk's name carries a hash of its content, so its
     * URL is new whenever its bytes are, and it is written without a query, the way the modules
     * import it, or the preload would fetch a second copy.
     */
    private static function url(bool $published, string $file, string $version): string
    {
        $key = str_starts_with($file, 'chunks/') ? '' : $version;

        return ($published ? asset('vendor/wirekit/auto/'.$file) : url('/wirekit/auto/'.$file)).($key === '' ? '' : '?v='.$key);
    }

    /**
     * Whether the bundle is served from the published copy, which it is while that copy's
     * manifest is current, or from the package's route. Decided once for the whole directory,
     * since a chunk of one build has another name in the next. The manifest carries a fingerprint
     * of every file of its build, so a copy published before an update differs from the package's
     * manifest even where the update changed only the code inside one factory's module.
     */
    private static function published(): bool
    {
        return ! AssetTags::publishedIsStale(public_path('vendor/wirekit/auto/manifest.json'), AssetTags::distPath('auto/manifest.json'));
    }

    /**
     * The bundle's manifest, read once per process, or false where the package carries none.
     *
     * @return array{build: string, runtime: array{file: string, preload: list<string>}, factories: array<string, array{file: string, preload: list<string>}>}|false
     */
    private static function manifest(): array|false
    {
        if (self::$manifest !== null) {
            return self::$manifest;
        }

        $path = AssetTags::distPath('auto/manifest.json');
        $decoded = $path === null ? null : json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded) || ! isset($decoded['runtime']['file'], $decoded['factories']) || ! is_array($decoded['factories'])
            || ! is_string($decoded['build'] ?? null) || preg_match('/^[0-9a-f]{20}$/', $decoded['build']) !== 1) {
            return self::$manifest = false;
        }

        /** @var array{build: string, runtime: array{file: string, preload: list<string>}, factories: array<string, array{file: string, preload: list<string>}>} $manifest */
        $manifest = $decoded;

        return self::$manifest = $manifest;
    }

    /** Forget the manifest read, for a test that swaps the file. */
    public static function reset(): void
    {
        self::$manifest = null;
    }
}
