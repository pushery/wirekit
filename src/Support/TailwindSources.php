<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Pushery\WireKit\ComponentRegistry;

/**
 * One Tailwind source per component: what Tailwind needs to see to build that component's
 * utilities, and nothing else.
 *
 * The integration scans every template of the package, so an application pays for the utilities
 * of the whole catalog whatever it renders. A source per component lets it scan only what it
 * uses. Each one covers the component's whole closure: its own template, its sub-components, the
 * components and partials it renders, the views its class renders, and the runtime class list
 * when one of those templates resolves intent and surface classes in PHP. A component that starts
 * rendering another brings that one's utilities along in its own source, so a list of components
 * an application declares stays right across an upgrade.
 *
 * The content is every whitespace-separated token of those templates, deduplicated and sorted.
 * A Tailwind candidate never contains whitespace, so splitting there loses none, and comments stay
 * in because Tailwind reads comments too (the runtime class list is written in one).
 */
final class TailwindSources
{
    /** Where the generated sources live, relative to the package root. */
    public const DIRECTORY = 'resources/tailwind';

    /** The template listing the classes `VariantResolver` builds at runtime. */
    private const RUNTIME_CLASSES = '_safelist.blade.php';

    /**
     * The generated source of every registry component, keyed by component name.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        $sources = [];

        foreach (array_keys(ComponentRegistry::all()) as $name) {
            $sources[$name] = self::contentsFor($name);
        }

        ksort($sources);

        return $sources;
    }

    /**
     * The generated source of one component.
     */
    public static function contentsFor(string $component): string
    {
        $tokens = [];

        foreach (self::templatesOf($component) as $template) {
            foreach (preg_split('/\s+/', (string) file_get_contents($template), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
                $tokens[$token] = true;
            }
        }

        $tokens = array_keys($tokens);
        sort($tokens, SORT_STRING);

        return implode("\n", $tokens)."\n";
    }

    /**
     * Every template in a component's closure, as absolute paths, sorted.
     *
     * @return list<string>
     */
    public static function templatesOf(string $component): array
    {
        $views = self::viewsDirectory();
        $start = ComponentRegistry::existingBladeFilePath($component);

        if ($start === null) {
            return [];
        }

        $queue = [$start];

        // Views a class-based component renders by name, a fallback among them.
        $class = ComponentRegistry::componentClass($component);

        if ($class !== null) {
            $file = (new \ReflectionClass($class))->getFileName();

            if (is_string($file)) {
                foreach (self::namedViewsIn((string) file_get_contents($file)) as $view) {
                    $queue[] = $view;
                }
            }
        }

        // Views outside `components/` that carry the component's name: the Livewire pagination
        // views an application points `paginationView()` at belong to `pagination`.
        foreach (glob($views.'/'.$component.'/*.blade.php') ?: [] as $view) {
            $queue[] = $view;
        }

        $seen = [];

        while ($queue !== []) {
            // One spelling per file: the registry answers `src/../resources/…`, the lookups here
            // `resources/…`, and without this the same template is read twice.
            $template = realpath((string) array_pop($queue));

            if ($template === false || isset($seen[$template])) {
                continue;
            }

            $seen[$template] = true;

            // Read for what it RENDERS, so without its comments: a note that mentions another
            // component is not a use of it. The tokens keep the comments (see contentsFor()).
            $source = self::withoutComments((string) file_get_contents($template));

            // The components it renders, dotted sub-components and partials included.
            preg_match_all('/<x-wirekit(?:::|-)([a-z0-9.-]+)/', $source, $rendered);

            foreach (array_unique($rendered[1]) as $name) {
                $path = ComponentRegistry::existingBladeFilePath(rtrim($name, '.'))
                    ?? self::componentPath($views, rtrim($name, '.'));

                if ($path !== null) {
                    $queue[] = $path;
                }
            }

            // The partials it includes.
            foreach (self::namedViewsIn($source) as $view) {
                $queue[] = $view;
            }

            // A component's sub-components live in the directory named after it, and using the
            // component means using its parts.
            $family = substr($template, 0, -strlen('.blade.php'));

            foreach (glob($family.'/*.blade.php') ?: [] as $part) {
                $queue[] = $part;
            }

            // Intent and surface classes resolved in PHP never appear in the template; the
            // runtime class list carries them for any closure that resolves them.
            if (str_contains($source, 'VariantResolver')) {
                $queue[] = $views.'/'.self::RUNTIME_CLASSES;
            }
        }

        $templates = array_keys($seen);
        sort($templates, SORT_STRING);

        return $templates;
    }

    /**
     * A template without its Blade comments, its PHP block comments and its whole-line `//`
     * comments. A trailing `//` is left alone, because in markup it is usually a URL.
     */
    private static function withoutComments(string $source): string
    {
        $source = (string) preg_replace('#\{\{--.*?--\}\}#s', '', $source);
        $source = (string) preg_replace('#/\*.*?\*/#s', '', $source);

        return (string) preg_replace('#^\s*//.*$#m', '', $source);
    }

    /**
     * The template path of a component name the registry does not resolve (a partial).
     */
    private static function componentPath(string $views, string $name): ?string
    {
        $path = $views.'/components/'.str_replace('.', '/', $name).'.blade.php';

        return is_file($path) ? $path : null;
    }

    /**
     * The package views a source names as `'wirekit::a.b'`, as absolute paths.
     *
     * @return list<string>
     */
    private static function namedViewsIn(string $source): array
    {
        preg_match_all("/['\"]wirekit::([a-z0-9._-]+)['\"]/", $source, $named);

        $views = self::viewsDirectory();
        $paths = [];

        foreach (array_unique($named[1]) as $view) {
            $path = $views.'/'.str_replace('.', '/', $view).'.blade.php';

            if (is_file($path)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    private static function viewsDirectory(): string
    {
        return dirname(__DIR__, 2).'/resources/views';
    }
}
