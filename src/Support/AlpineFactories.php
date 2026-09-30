<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Pushery\WireKit\Charts\ChartJsAdapter;

/**
 * The Alpine factories a component starts, for an application that registers only the ones its
 * views use.
 *
 * `dist/wirekit.esm.js` exports every factory by name next to `register()`, so a bundler keeps
 * the ones an application imports and drops the rest. What a developer cannot see from the tag is
 * which names a component needs: a dropdown starts factories for its trigger and its submenus as
 * well, and a component that renders another one, a status dot inside its tooltip, needs that
 * one's factory too. This reads them from the templates: every `x-data` that starts a WireKit
 * factory in the component's own views, its sub-components and partials, and the components they
 * render, kept to the names the ES module exports, since those are what `register()` can take.
 *
 * @internal Read by `wirekit:export-api-map`; the list is published there as `factories`.
 */
final class AlpineFactories
{
    /** @var array<string, list<string>> */
    private static array $cache = [];

    /** @var list<string>|null */
    private static ?array $exported = null;

    /**
     * The factories a component starts, sorted.
     *
     * @return list<string>
     */
    public static function of(string $component): array
    {
        if (isset(self::$cache[$component])) {
            return self::$cache[$component];
        }

        $views = dirname(__DIR__, 2).'/resources/views/components';
        $queue = [$component];
        $seen = [];
        $found = [];

        while ($queue !== []) {
            $name = (string) array_shift($queue);

            if (isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;

            foreach (self::filesOf($views, $name) as $file) {
                $source = BladeParser::blankComments((string) file_get_contents($file));

                // The parentheses are optional: Alpine accepts a bare factory name.
                preg_match_all('/x-data="\s*\{?\s*(wirekit[A-Z][A-Za-z0-9]*)/', $source, $started);

                foreach ($started[1] as $factory) {
                    $found[$factory] = true;
                }

                // The chart asks its adapter for the factory, and the one the ES module carries
                // is the Chart.js adapter's; ApexCharts ships its own bundle.
                if (str_contains($source, '{{ $alpineComponent }}')) {
                    $found[(new ChartJsAdapter)->alpineComponent()] = true;
                }

                preg_match_all('/<x-wirekit::([a-z0-9.-]+)/', $source, $tags);
                preg_match_all("/@include\\(\\s*'wirekit::components\\.([a-z0-9.-]+)'/", $source, $includes);

                array_push($queue, ...$tags[1], ...$includes[1]);

                // The chart is a class component, written with a hyphen rather than `::`.
                if (preg_match('/<x-wirekit-chart\b/', $source) === 1) {
                    $queue[] = 'chart';
                }
            }
        }

        $factories = array_values(array_intersect(array_keys($found), self::exported()));
        sort($factories);

        return self::$cache[$component] = $factories;
    }

    /**
     * The factory names `resources/js/wirekit.esm.js` exports, read from its export block.
     *
     * @return list<string>
     */
    public static function exported(): array
    {
        if (self::$exported !== null) {
            return self::$exported;
        }

        $path = dirname(__DIR__, 2).'/resources/js/wirekit.esm.js';
        $entry = is_file($path) ? (string) file_get_contents($path) : '';
        preg_match('/export \{\s*(.*?)\s*\};/s', $entry, $block);
        preg_match_all('/^\s*(wirekit[A-Z][A-Za-z0-9]*)\s*,?\s*$/m', $block[1] ?? '', $names);

        return self::$exported = array_values(array_unique($names[1]));
    }

    /**
     * A component's template and the templates of its sub-components.
     *
     * @return list<string>
     */
    private static function filesOf(string $views, string $name): array
    {
        $path = $views.'/'.str_replace('.', '/', $name);
        $files = is_file($path.'.blade.php') ? [$path.'.blade.php'] : [];

        if (is_dir($path)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file instanceof \SplFileInfo && str_ends_with($file->getFilename(), '.blade.php')) {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }
}
