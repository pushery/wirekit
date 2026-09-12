<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use RuntimeException;

/**
 * Filesystem writes that FAIL LOUDLY.
 *
 * PHP's `mkdir()`, `copy()`, `file_put_contents()` and friends return `false` on failure and
 * raise a warning that a console command's output never shows. Ignore the return and a
 * permission error, a full disk or a read-only mount arrives as SUCCESS: the command prints
 * "published", exits 0, and nothing is on disk.
 *
 * ⚠️ THAT IS THE WORST SHAPE A FAILURE CAN TAKE HERE, because the developer's next step is to
 * use what was supposedly published. They hit a missing asset, and the command that was
 * supposed to put it there has already told them it did. The publish commands did this at
 * seven call sites — every `mkdir`, `copy` and `file_put_contents` in `PublishFontsCommand`
 * and `PublishIconsCommand`.
 *
 * The `@` suppression is deliberate and paired: PHP's own warning is discarded because the
 * exception carries the same information plus the path, and a bare warning to STDERR in the
 * middle of a progress table is noise a reader scrolls past.
 */
final class FileWrite
{
    /**
     * Create a directory, including parents. A directory that already exists is not a failure.
     *
     * @throws RuntimeException when the directory does not exist afterwards
     */
    public static function ensureDirectory(string $path, int $permissions = 0o755): void
    {
        if (is_dir($path)) {
            return;
        }

        // The result is re-checked with `is_dir()` rather than trusted, because a concurrent
        // process can win the race and `mkdir()` then returns false for a directory that IS
        // there. Reporting that as a failure would be a different lie.
        if (! @mkdir($path, $permissions, true) && ! is_dir($path)) {
            throw new RuntimeException(sprintf(
                'Could not create the directory %s. Check that the parent is writable.',
                $path
            ));
        }
    }

    /**
     * Copy a file, creating the destination's directory when needed.
     *
     * @throws RuntimeException when the copy did not happen
     */
    public static function copy(string $source, string $destination): void
    {
        self::ensureDirectory(dirname($destination));

        if (! @copy($source, $destination)) {
            throw new RuntimeException(sprintf(
                'Could not copy %s to %s. Check that the destination is writable.',
                $source,
                $destination
            ));
        }
    }

    /**
     * Write a file, creating its directory when needed.
     *
     * @throws RuntimeException when the write did not happen or was truncated
     */
    public static function put(string $path, string $contents): void
    {
        self::ensureDirectory(dirname($path));

        $written = @file_put_contents($path, $contents);

        // `false` is the failure PHP documents; a SHORT write is the one it does not — a full
        // disk returns the byte count it managed rather than false, so a file that exists and
        // is half its content passes a `!== false` check.
        if ($written === false || $written !== strlen($contents)) {
            throw new RuntimeException(sprintf(
                'Could not write %s (%s). Check free space and that the path is writable.',
                $path,
                $written === false ? 'the write failed' : sprintf('wrote %d of %d bytes', $written, strlen($contents))
            ));
        }
    }

    /**
     * Best-effort delete for a CLEANUP path, where failing is not worth reporting.
     *
     * ⚠️ This exists so the difference is visible in the call, and it has exactly one correct
     * use: removing a temp file while already handling a failure. Throwing there would replace
     * the exception the caller is about to raise — the one that says what actually went wrong
     * — with one about the leftover, which is the less useful of the two.
     *
     * Everywhere else, use `delete()`. A silent removal failure in a normal path is the same
     * defect this whole class is about, one verb along.
     */
    public static function deleteBestEffort(string $path): void
    {
        if (is_dir($path)) {
            @rmdir($path);

            return;
        }

        @unlink($path);
    }

    /**
     * Delete a file or an empty directory.
     *
     * @throws RuntimeException when the entry is still there afterwards
     */
    public static function delete(string $path): void
    {
        $removed = is_dir($path) ? @rmdir($path) : @unlink($path);

        if (! $removed && file_exists($path)) {
            throw new RuntimeException(sprintf(
                'Could not remove %s. Check the permissions on its parent directory.',
                $path
            ));
        }
    }
}
