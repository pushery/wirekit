<?php

declare(strict_types=1);

namespace Pushery\WireKit\Icons;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Where an icon package actually keeps its SVGs.
 *
 * Written down as a path, this answer is wrong on a schedule. `blade-lucide-icons` v2
 * moved its icons from `resources/svg` into `resources/svg/icons`, and the directory it
 * vacated still exists — so an `is_dir()` check kept answering yes over an empty folder.
 * `wirekit:publish-icons lucide` then passed its own guard, recursively copied a
 * directory whose only content was a nested one, and reported success. The developer got
 * `icons/lucide/icons/*.svg` where every other preset gives them `icons/lucide/*.svg`,
 * and nothing anywhere said so.
 *
 * That is the shape worth naming: the check was not missing, it was aimed one level away
 * from the thing it was protecting. Existence is not the question. The question is where
 * the files are, and the only honest way to answer it is to look.
 *
 * The rename happens in somebody else's repository, on their release schedule, so this
 * has to keep working without anyone here noticing it happened.
 */
final class IconSourceLocator
{
    /**
     * The deepest directory under the package that actually holds `.svg` files.
     *
     * Returns null when the package is absent or ships no SVGs at all — a caller needs to
     * tell "not installed" apart from "installed and empty", and both are null here only
     * because both mean the same thing to a caller: there is nothing to publish.
     *
     * @param  string  $packageDir  absolute path to the installed composer package
     */
    public static function locate(string $packageDir): ?string
    {
        $base = rtrim($packageDir, '/').'/resources';

        if (! is_dir($base)) {
            return null;
        }

        $counts = [];

        $walk = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($walk as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'svg') {
                continue;
            }

            $dir = $file->getPath();
            $counts[$dir] = ($counts[$dir] ?? 0) + 1;
        }

        if ($counts === []) {
            return null;
        }

        // The directory holding the MOST SVGs, not the first one found. Several of these
        // packages ship a secondary set beside the main one — Lucide's `lab` folder is the
        // example — and a walk that stopped at its first hit would publish the sideshow.
        arsort($counts);

        return (string) array_key_first($counts);
    }
}
