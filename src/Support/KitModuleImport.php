<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * The application's own module that registers the kit's components from the ES module build.
 *
 * Next to `@wirekitScripts` this is the second way to load the kit's scripts: a module of the
 * application imports `dist/wirekit.esm.js` and hands Alpine its default export, or calls
 * `register()` with the components the views render, and the layout carries no directive (see
 * "Importing only the components you use" on the dependencies page). The verify command and the
 * install command both ask this class, so neither reports or adds a directive the application
 * left out on purpose.
 *
 * A module counts when an `import` statement binds the build's default export, its namespace or
 * `register`. One that only takes a helper such as `contrast` registers no component and does
 * not count, nor does an import inside a comment or a type-only import.
 *
 * @internal
 */
final class KitModuleImport
{
    /** The extensions of the modules read. */
    private const EXTENSIONS = ['js', 'mjs', 'ts', 'mts', 'jsx', 'tsx'];

    /**
     * The first module under the application's `resources/js` that registers the kit's
     * components, relative to the project, or null.
     */
    public static function inApplication(): ?string
    {
        $found = self::in(resource_path('js'));

        return $found === null ? null : str_replace(base_path().DIRECTORY_SEPARATOR, '', $found);
    }

    /** The first module under `$directory` that registers the kit's components, or null. */
    public static function in(string $directory): ?string
    {
        if (! is_dir($directory)) {
            return null;
        }

        $modules = [];

        try {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($files as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile() && in_array(strtolower($file->getExtension()), self::EXTENSIONS, true)) {
                    $modules[] = $file->getPathname();
                }
            }
        } catch (\UnexpectedValueException) {
            // A directory that vanished or turned unreadable during the walk: what was read
            // so far still answers.
        }

        // Sorted, so the module named in a report is the same on every run and every machine.
        sort($modules);

        foreach ($modules as $module) {
            $source = @file_get_contents($module);

            if (is_string($source) && self::registers($source)) {
                return $module;
            }
        }

        return null;
    }

    /** Whether this module source imports the build's default export, its namespace or `register`. */
    public static function registers(string $source): bool
    {
        $code = JsSource::withoutComments($source);

        // The clause between `import` and `from`, over several lines where the names stand in a
        // list, and the specifier, which names the kit's ES module build.
        preg_match_all(
            '/\bimport\s+([^\'";]*?)\s*\bfrom\s*([\'"])[^\'"]*\bwirekit\.esm\.js(?:\?[^\'"]*)?\2/s',
            $code,
            $imports,
            PREG_SET_ORDER,
        );

        foreach ($imports as $import) {
            $clause = trim($import[1]);

            // `import type { … }` is erased at compile time and loads nothing.
            if (preg_match('/^type\s/', $clause) === 1) {
                continue;
            }

            // The default export, alone or before a list (`import WireKit, { … }`).
            if (preg_match('/^[A-Za-z_$][\w$]*\s*(?:,|$)/', $clause) === 1) {
                return true;
            }

            // The namespace, which carries the default export and `register`.
            if (preg_match('/^\*\s*as\s+[A-Za-z_$][\w$]*$/', $clause) === 1) {
                return true;
            }

            // `register` among the names, renamed or not. `registerHlsEngine` is another name.
            if (preg_match('/\{[^}]*(?<![\w$])register(?:\s+as\s+[A-Za-z_$][\w$]*)?\s*(?:,|\})/', $clause) === 1) {
                return true;
            }
        }

        return false;
    }
}
