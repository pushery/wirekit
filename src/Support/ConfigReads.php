<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Pushery\WireKit\WireKit;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The paths below `wirekit.components.` that the package reads, and whether a path set by an
 * application reaches one of them.
 *
 * `WireKit::defaults()` writes every key it is given into the configuration, and a component takes a
 * default from there only where it asks for that path, usually as the default of a prop:
 * `'size' => config('wirekit.components.button.size', 'md')`. A key nothing asks for changes nothing.
 *
 * The list comes from `resources/catalog/config-reads.php`, which the package's build writes from
 * {@see scan()}. Scanning the views when an application boots would cost tens of milliseconds on
 * every request in development, and a generated file costs nothing.
 */
final class ConfigReads
{
    /** @var list<string>|null */
    private static ?array $catalog = null;

    /**
     * The paths the package reads, below `wirekit.components.`, as the build wrote them.
     *
     * @return list<string>
     */
    public static function catalog(): array
    {
        if (self::$catalog === null) {
            $file = dirname(__DIR__, 2).'/resources/catalog/config-reads.php';
            $paths = is_file($file) ? require $file : [];
            $catalog = [];

            foreach (is_array($paths) ? $paths : [] as $path) {
                if (is_string($path)) {
                    $catalog[] = $path;
                }
            }

            self::$catalog = $catalog;
        }

        return self::$catalog;
    }

    /**
     * Whether something reads the path, a section below it, or the section the path lies in.
     * A sub-component reads `accordion.item.open` below the section `accordion.item`, and
     * `activity-row` reads `kinds` as one map, which takes every key set inside it. `classes`
     * counts as read everywhere: {@see WireKit::resolveClasses()} reads it for every component
     * under a name it builds while it runs.
     */
    public static function reaches(string $path): bool
    {
        if (in_array('classes', explode('.', $path), true)) {
            return true;
        }

        foreach (self::catalog() as $read) {
            if ($read === $path || str_starts_with($read, $path.'.') || str_starts_with($path, $read.'.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * The paths read below a component, relative to it and sorted: `intent`, `size` and `surface`
     * for `button`, `collapsible.open` and `group.lazy` among those of `sidebar`. Empty for a
     * component that reads nothing from the configuration.
     *
     * @return list<string>
     */
    public static function keysOf(string $component): array
    {
        $keys = [];

        foreach (self::catalog() as $read) {
            if (str_starts_with($read, $component.'.')) {
                $keys[] = substr($read, strlen($component) + 1);
            }
        }

        sort($keys);

        return $keys;
    }

    /**
     * Every path a package tree reads: each `config('wirekit.components.…')` in the component
     * views and in `src`, with comments removed first, since a call quoted in a comment reads
     * nothing. Sorted, each path once.
     *
     * @return list<string>
     */
    public static function scan(string $root): array
    {
        $paths = [];

        foreach (['resources/views/components', 'src'] as $directory) {
            if (! is_dir($root.'/'.$directory)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, RecursiveDirectoryIterator::SKIP_DOTS));

            foreach ($files as $file) {
                if (! $file instanceof \SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $source = (string) file_get_contents($file->getPathname());

                if (! str_contains($source, 'wirekit.components.')) {
                    continue;
                }

                $source = (string) preg_replace(['~/\*.*?\*/~s', '~^\s*//.*$~m', '~\{\{--.*?--\}\}~s'], '', $source);

                if (preg_match_all('~config\(\s*[\'"]wirekit\.components\.([A-Za-z0-9_.-]+)[\'"]~', $source, $matches) > 0) {
                    foreach ($matches[1] as $path) {
                        $paths[$path] = true;
                    }
                }
            }
        }

        $paths = array_keys($paths);
        sort($paths);

        return array_values(array_map(strval(...), $paths));
    }
}
