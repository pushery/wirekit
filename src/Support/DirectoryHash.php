<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Compare a bundled source directory against its published copy by file content.
 *
 * Shared by `wirekit:publish-fonts` (skip only when already up to date, overwrite
 * on drift) and `wirekit:verify` (WARN when the published fonts are stale) so the
 * two commands agree on what "already published" means — the freshness answer the
 * doctor already gives for wirekit.css / wirekit.js, now for the font tree too.
 */
final class DirectoryHash
{
    /**
     * True when every file under $source exists under $target with identical
     * bytes — i.e. the published copy mirrors the bundled release. Extra files in
     * $target (a family the config no longer names) are ignored; that is `--prune`'s
     * concern, not freshness.
     *
     * $transform maps a source file's bytes to what the publisher would actually
     * write, and exists because one file is no longer copied verbatim: a family's
     * stylesheet has `wirekit.fonts.display` substituted into it on the way out.
     * Without it, the question "is the published copy current?" would compare the
     * published file against bytes nobody ever writes, and every non-default
     * `font-display` would read as permanent drift in both callers at once.
     *
     * @param  null|callable(string $relativePath, string $contents): string  $transform
     */
    public static function matches(string $source, string $target, ?callable $transform = null): bool
    {
        if (! is_dir($source) || ! is_dir($target)) {
            return false;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                continue;
            }

            $relative = substr($item->getPathname(), strlen($source) + 1);
            $destination = $target.DIRECTORY_SEPARATOR.$relative;

            if (! is_file($destination)) {
                return false;
            }

            if ($transform === null) {
                if (md5_file($item->getPathname()) !== md5_file($destination)) {
                    return false;
                }

                continue;
            }

            $intended = $transform($relative, (string) file_get_contents($item->getPathname()));

            if (md5($intended) !== md5_file($destination)) {
                return false;
            }
        }

        return true;
    }
}
