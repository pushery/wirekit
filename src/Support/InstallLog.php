<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * The append-only record `wirekit:install` leaves behind so `--rollback` can undo it.
 *
 * It is append-only within a window, and the window is the point. A session records the
 * PRIOR CONTENT of every file the install touched — which on a reinstall means the published
 * asset bundles, roughly ten megabytes, written into the developer's project root. Nothing
 * shortened it and nothing warned, because an append is not an error. Measured in this
 * package's own test skeleton before the cap: 33 sessions, 321 MB, a single line of 9.7 MB.
 *
 * The reader is where that becomes expensive rather than merely untidy. `--rollback` loads
 * the whole file to use its LAST line, so on a log that has been allowed to grow, the command
 * that undoes a bad install is the one that runs out of memory — a safety net failing exactly
 * when it is reached for.
 *
 * Only the last session is ever replayed, so keeping five costs nothing that was being used
 * and leaves a developer enough history to see what the previous installs touched.
 */
final class InstallLog
{
    /**
     * How many sessions survive a write.
     */
    public const SESSIONS_KEPT = 5;

    /**
     * Append one session and drop everything past the window.
     *
     * @param  string  $path  Absolute path to the log.
     * @param  string  $session  One JSON-Lines session, without its trailing newline.
     */
    public static function append(string $path, string $session): void
    {
        file_put_contents($path, rtrim($session, "\r\n")."\n", FILE_APPEND);

        self::trim($path);
    }

    /**
     * The newest session line, read WITHOUT loading the log.
     *
     * `--rollback` used to do `explode("\n", file_get_contents($path))` and then take
     * `end()` of it — reading the whole file, doubling it in an array, and using one line.
     * Measured against a log holding its five retained sessions of this package's own
     * publishable tree (99 files, 6.92 MB on disk, 8.95 MB per line once binaries are
     * base64'd): a 44.7 MB log peaked at **91.5 MB** and died outright at
     * `memory_limit=64M`. The command that undoes a bad install was the one that ran out
     * of memory — a safety net failing exactly when it is reached for.
     *
     * This file's own docblock already described that defect. The WRITE path was fixed with
     * a bounded ring; the reader it was written about was not, which is what this closes.
     * The same streaming shape as `trim()` below peaks at 30.9 MB on that file and clears
     * a 64M limit.
     *
     * @return string|null the last non-empty line, or null when there is none
     */
    public static function lastSessionLine(string $path): ?string
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return null;
        }

        $last = null;

        // One line held at a time. `fgets` is what makes this bounded by the longest LINE
        // rather than by the file — and the longest line is the one the caller wanted anyway.
        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");

            if ($line !== '') {
                $last = $line;
            }
        }

        fclose($handle);

        return $last;
    }

    /**
     * Keep only the newest SESSIONS_KEPT lines.
     *
     * Read line by line rather than with `file_get_contents`, and that is the whole point of
     * doing it here: a log that has already been allowed to grow is exactly the one this has
     * to be able to open. Slurping it in order to shorten it would fail on the only file that
     * ever needs shortening.
     *
     * The rewrite goes through a sibling temp file and one rename, so an interrupted trim
     * leaves the old log intact rather than a half-written one. A truncated JSON-Lines file is
     * worse than a long one: `--rollback` would parse a cut-off last session and restore half
     * a file.
     */
    public static function trim(string $path): void
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return;
        }

        // A ring of the last N lines. Bounded memory whatever the file holds — bar the lines
        // themselves, and those are the ones being kept either way.
        $ring = [];
        $total = 0;

        while (($line = fgets($handle)) !== false) {
            if (trim($line) === '') {
                continue;
            }

            $ring[] = rtrim($line, "\r\n");
            $total++;

            if (count($ring) > self::SESSIONS_KEPT) {
                array_shift($ring);
            }
        }

        fclose($handle);

        if ($total <= self::SESSIONS_KEPT) {
            return;
        }

        $temp = $path.'.trim';
        $out = @fopen($temp, 'wb');

        if ($out === false) {
            return;
        }

        foreach ($ring as $line) {
            fwrite($out, $line."\n");
        }

        fclose($out);

        @rename($temp, $path);
    }
}
