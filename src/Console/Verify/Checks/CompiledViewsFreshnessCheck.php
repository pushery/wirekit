<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console\Verify\Checks;

use Pushery\WireKit\Console\Verify\VerifyCheck;

/**
 * One check `wirekit:verify` runs, in its environment tier. The command decides the order and
 * prints the totals; this class decides what it reports.
 *
 * @internal
 */
final class CompiledViewsFreshnessCheck extends VerifyCheck
{
    public function run(): void
    {
        $this->checkCompiledViewsFreshness();
    }

    /**
     * Detects compiled-view staleness — the canonical reason a developer
     * test sees "the new prop isn't there" even after their Blade source
     * carries it. Laravel's `storage/framework/views/` retains pre-edit
     * compiled templates whose filemtime granularity (1-second) AND
     * filesystem-cache lag can let stale output survive a fast file-edit
     * cycle. The first diagnostic chain a developer walks is "did I wire
     * the prop?" — this check short-circuits that and points at
     * `php artisan view:clear`.
     *
     * Threshold: 60-second buffer between newest source mtime and
     * newest compiled-view mtime. Below the threshold = no warning
     * (normal fast-edit window). Above = WARN with the actionable hint.
     *
     * False-positive mitigation: this is WARN (not FAIL), the recommended
     * action is non-destructive, and slow filesystems (NFS / Docker on
     * macOS) get the same advice they'd give themselves anyway. The
     * threshold is tuned to bite on "I edited an hour ago and the test
     * still fails" — not on "I just hit save".
     */
    private function checkCompiledViewsFreshness(): void
    {
        $compiledDir = storage_path('framework/views');
        $sourceDir = resource_path('views');

        // No compiled views = fresh state (Laravel will compile on the
        // next render). Silent skip — nothing meaningful to report.
        if (! is_dir($compiledDir) || ! is_dir($sourceDir)) {
            return;
        }

        // Only *.php, which is what a compiled Blade template is. Without the filter the
        // scan also saw the shipped `.gitignore`, so a freshly cleared cache never looked
        // empty — and the advice became a loop: `view:clear` produces exactly the state
        // the warning then reports, so following it re-triggers the warning that sent you
        // there. A developer runs the suggested command, sees the same message, and
        // reasonably concludes the tool is broken.
        $newestCompiled = $this->newestMtimeUnder($compiledDir, ['php']);
        if ($newestCompiled === 0) {
            // Compiled directory holds no templates — the fresh state after view:clear.
            return;
        }

        $newestSource = $this->newestMtimeUnder($sourceDir, ['php']);
        if ($newestSource === 0) {
            return;
        }

        // The PACKAGE counts as a source here, and leaving it out is what made this check
        // unable to see the one event it most needs to: a `composer update pushery/wirekit`
        // moves the package's views and touches nothing under `resources/views/`, so the
        // comparison above stayed green while every compiled template still held the
        // previous version's markup. That is precisely the state a developer is in right
        // after an upgrade — and the state in which `view:clear` is the answer.
        $newestSource = max($newestSource, $this->packageNewestMtime());

        $lagSeconds = $newestSource - $newestCompiled;
        $thresholdSeconds = 60;

        if ($lagSeconds < $thresholdSeconds) {
            $this->reportPass('Compiled views are fresh (no staleness detected)');

            return;
        }

        $this->reportWarn(sprintf(
            'Compiled views may be stale (a source newer than storage/framework/views/ by %s — '
            .'your own resources/views/, or the installed package after an update).',
            $this->humanDuration($lagSeconds)
        ));
        $this->line('  Run: php artisan view:clear');
        $this->line('  This is the canonical fix when a developer test asserts a Blade prop / class that');
        $this->line('  was just wired in source but the assertion still fails — the compiled-view cache');
        $this->line('  retained the pre-edit template.');
    }

    /**
     * Recursive newest-mtime scanner with an optional extension filter.
     * Used by checkCompiledViewsFreshness() for both the source and
     * compiled directory traversals.
     *
     * @param  list<string>  $extensionsAllowlist  Empty = every file qualifies.
     */
    private function newestMtimeUnder(string $dir, array $extensionsAllowlist = []): int
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $newest = 0;
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            if ($extensionsAllowlist !== [] && ! in_array($file->getExtension(), $extensionsAllowlist, true)) {
                continue;
            }
            $mtime = $file->getMTime();
            if ($mtime > $newest) {
                $newest = $mtime;
            }
        }

        return $newest;
    }

    /**
     * Pretty-print a duration in seconds as "Xh Ym" / "Xm Ys" / "Xs".
     * Used by the compiled-views-staleness check to produce the
     * actionable lag-amount in the WARN message.
     */
    private function humanDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.'s';
        }
        if ($seconds < 3600) {
            $minutes = (int) floor($seconds / 60);
            $remainder = $seconds % 60;

            return $remainder > 0 ? "{$minutes}m {$remainder}s" : "{$minutes}m";
        }
        $hours = (int) floor($seconds / 3600);
        $minutes = (int) floor(($seconds % 3600) / 60);

        return $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h";
    }
}
