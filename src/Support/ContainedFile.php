<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * Resolve a requested path to a regular file inside one directory, or to nothing.
 *
 * The package serves files from its own tree through routes, and the font route takes part of
 * the path from the request. Both checks run on the resolved path: `realpath()` collapses `..`
 * and follows links, and the result has to sit BELOW the root. That is a comparison against the
 * root plus a separator. Against the bare root, `resources/fonts-extra/x.css` and
 * `resources/fonts.css` both begin with `resources/fonts` and would pass as if they were inside.
 */
final class ContainedFile
{
    /**
     * The real path of the regular file `$relative` names under `$root`, or null when the root or
     * the file does not exist, the path names a directory, or it resolves outside the root.
     */
    public static function resolve(string $root, string $relative): ?string
    {
        $base = realpath($root);
        $file = realpath($root.DIRECTORY_SEPARATOR.$relative);

        if ($base === false || $file === false || ! is_file($file)) {
            return null;
        }

        // rtrim() keeps a filesystem root ('/') from becoming '//', which no path starts with.
        return str_starts_with($file, rtrim($base, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR) ? $file : null;
    }
}
