<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console\Verify\Checks;

use Pushery\WireKit\Console\Verify\VerifyCheck;
use Pushery\WireKit\WireKit;

/**
 * One check `wirekit:verify` runs, in its environment tier. The command decides the order and
 * prints the totals; this class decides what it reports.
 *
 * @internal
 */
final class SilentValidationTyposCheck extends VerifyCheck
{
    public function run(): void
    {
        $this->checkSilentValidationTypos();
    }

    /**
     * Surface silent prop typos detected in the Laravel log.
     *
     * The StrictnessGate emits `local.ERROR: WireKit [...]`
     * lines in HTTP dev mode when a developer passes an invalid prop
     * value (the gate logs + renders the fallback instead of throwing).
     * Without this scan a typo can sit in production code for weeks —
     * the page renders with the fallback and the developer never sees
     * the log.
     *
     * SAFE-DEGRADE contract — this check is an OPTIONAL helper, never
     * required, never blocks. Every branch handles its failure mode
     * gracefully:
     *   - Log file missing → INFO ("scan skipped — log not at default path")
     *   - Log file unreadable → INFO ("scan skipped — permission denied")
     *   - Log level filters below WARNING → INFO ("scan saw nothing —
     *     LOG_LEVEL may filter strict-validation writes")
     *   - Custom log channel (Slack, daily, stack-with-others) → INFO
     *     ("scan skipped — single-file scan can't see custom channels")
     *   - Found WireKit lines → WARN with example count + first 3 lines
     *   - Found none → PASS
     * The check NEVER returns FAIL — the doctor's overall exit code is
     * unaffected.
     *
     * Opt-out: set `wirekit.doctor.scan_logs` to `false` in config.
     *
     * This line also offered a `--no-scan-logs` flag "registered separately", and no such
     * option is declared on the command — Symfony rejects an unknown one, so the sentence
     * handed a developer trying to quiet the check an error from the very command they
     * were quieting. The config key is the whole opt-out.
     */
    private function checkSilentValidationTypos(): void
    {
        // SAFE-DEGRADE outer guard — wrap the entire method body in a
        // try/catch so ANY unexpected error (filesystem race during
        // parallel test runs, transient permission issue, glob() / fopen()
        // edge case) silently degrades to an INFO line instead of aborting
        // the rest of the doctor's check pipeline. The doctor's contract
        // (see method docblock) is that this check NEVER blocks; we extend
        // the contract here to "NEVER aborts subsequent checks either".
        // Without this guard, a transient PHP warning escalated to
        // exception under Pest's strict error handling would abort
        // `handle()` mid-pipeline and leak as a flaky test ("Alpine plugin
        // cleanup hygiene" missing because the check never ran).
        try {
            $this->checkSilentValidationTyposBody();
        } catch (\Throwable $e) {
            $this->reportInfo(
                'silent-typo log scan skipped — transient I/O error during scan ('.
                $e::class.': '.mb_substr($e->getMessage(), 0, 100).'). '.
                'The scan is an optional helper; subsequent doctor checks are unaffected.'
            );
        }
    }

    private function checkSilentValidationTyposBody(): void
    {
        // Honor opt-out config — defaults to true (helper IS on by default
        // in dev environments; production developers may disable explicitly).
        $enabled = (bool) config('wirekit.doctor.scan_logs', true);
        if (! $enabled) {
            return;
        }

        // Custom log channels — when the developer routes logs anywhere
        // OTHER than the default single-file channel ('single' or 'daily'),
        // scanning a static file path would miss the writes entirely. Bail
        // out with an INFO instead of falsely reporting "no typos".
        $defaultChannel = (string) config('logging.default', 'stack');
        $stackChannels = (array) config('logging.channels.stack.channels', ['single']);
        $scannableChannels = ['single', 'daily', 'stack'];
        if (! in_array($defaultChannel, $scannableChannels, true)) {
            $this->reportInfo(sprintf(
                'silent-typo log scan skipped — LOG_CHANNEL=%s routes elsewhere (Slack / Papertrail / Sentry / custom). '.
                'Inspect that destination for `WireKit [...]` ERROR/WARNING lines manually.',
                $defaultChannel
            ));

            return;
        }
        if ($defaultChannel === 'stack') {
            $usableInStack = array_intersect($stackChannels, ['single', 'daily']);
            if ($usableInStack === []) {
                $this->reportInfo(
                    'silent-typo log scan skipped — LOG_STACK contains only non-file channels. '.
                    'Inspect the configured destinations for `WireKit [...]` ERROR/WARNING lines manually.'
                );

                return;
            }
        }

        // Resolve the log file path — Laravel's default 'single' channel
        // writes to storage/logs/laravel.log. The 'daily' channel rotates
        // per day (laravel-YYYY-MM-DD.log); scan today's file.
        $logDir = storage_path('logs');
        if (! is_dir($logDir)) {
            $this->reportInfo(
                'silent-typo log scan skipped — storage/logs directory missing (fresh install? skipped log writes?).'
            );

            return;
        }

        $logFiles = [];
        $singlePath = $logDir.'/laravel.log';
        if (is_file($singlePath) && is_readable($singlePath)) {
            $logFiles[] = $singlePath;
        }
        $dailyPattern = $logDir.'/laravel-*.log';
        $dailyFiles = glob($dailyPattern) ?: [];
        // Sort by mtime — race-safe via @suppression so a sibling worker
        // deleting a daily-rotated log between glob() and filemtime()
        // doesn't escalate a warning to a fatal under Pest's strict
        // error handling. Missing mtime sorts to 0 (treated as oldest).
        usort($dailyFiles, fn ($a, $b) => ((int) @filemtime($a)) <=> ((int) @filemtime($b)));
        foreach (array_slice($dailyFiles, -3) as $daily) {
            // Re-verify readability AT read-time (the file may have
            // vanished between glob() and now under parallel test runs).
            if (is_file($daily) && is_readable($daily)) {
                $logFiles[] = $daily;
            }
        }

        if ($logFiles === []) {
            $this->reportInfo(
                'silent-typo log scan skipped — no readable log files found at storage/logs/laravel*.log. '.
                'If your app writes logs elsewhere, this check is a no-op (expected behavior).'
            );

            return;
        }

        // Scan each log file for `local.ERROR: WireKit [...]` or
        // `local.WARNING: WireKit [...]` lines. We use a streaming
        // scanner — read line-by-line — so a huge log file doesn't
        // exhaust memory.
        //
        // BOUNDED BY AGE, and without that bound this check could never come back green.
        // It reads the whole log history, so a typo fixed weeks ago keeps its line in
        // laravel.log forever and the warning stays yellow for the life of the file. A
        // developer who did exactly what they were told sees no change, which teaches
        // them to stop reading this check — the one outcome a diagnostic cannot afford.
        //
        // The window is configurable, and 0 restores the old read-everything behavior for
        // anyone who wants it.
        $matches = [];
        $matchCount = 0;
        $recentCount = 0;
        $matchPattern = '/^\[(?<ts>[^\]]+)\] [a-z]+\.(ERROR|WARNING): WireKit \[/i';

        $windowHours = (int) config('wirekit.doctor.scan_logs_window_hours', 24);
        $cutoff = $windowHours > 0 ? time() - ($windowHours * 3600) : null;

        foreach ($logFiles as $path) {
            $fh = @fopen($path, 'r');
            if ($fh === false) {
                continue;
            }
            while (($line = fgets($fh)) !== false) {
                if (! preg_match($matchPattern, $line, $m)) {
                    continue;
                }

                $matchCount++;

                // A line whose timestamp will not parse counts as RECENT. The opposite
                // default would silently drop real findings to keep the output tidy,
                // which is the failure this whole check exists to catch, one level up.
                $stamp = strtotime($m['ts']);
                $isRecent = $cutoff === null || $stamp === false || $stamp >= $cutoff;

                if (! $isRecent) {
                    continue;
                }

                $recentCount++;

                // Examples from the RECENT set: a sample drawn from the whole history
                // would show lines the developer has already fixed.
                if (count($matches) < 3) {
                    $matches[] = trim($line);
                }
            }
            fclose($fh);
        }

        // Only recent lines decide the verdict. `$matchCount` stays for the message,
        // because "clean in the last 24h, 47 older" is worth saying — it tells the
        // reader the file is not empty without pretending the past is a finding.
        $olderCount = $matchCount - $recentCount;
        $matchCount = $recentCount;

        if ($matchCount === 0) {
            $this->reportPass(sprintf(
                'silent-typo log scan clean — no `WireKit [...]` ERROR/WARNING lines in %d log file(s)%s. '.
                '(If your env logs at level=critical/emergency only, fallback warnings get filtered out — that is expected.)',
                count($logFiles),
                $olderCount > 0
                    ? sprintf(' within the last %dh (%d older line(s) ignored)', $windowHours, $olderCount)
                    : ''
            ));

            return;
        }

        // Found something — surface as WARN, NEVER as FAIL. The doctor
        // shouldn't refuse to exit zero just because the developer left
        // a typo in a button. Show count + first 3 example lines.
        $exampleLines = implode("\n      ", array_map(
            fn ($l) => mb_substr($l, 0, 180).(mb_strlen($l) > 180 ? '…' : ''),
            $matches
        ));
        $this->reportWarn(sprintf(
            "silent prop-typo signals found in storage/logs: %d `WireKit [...]` ERROR/WARNING line(s). Examples:\n      %s\n      Fix each component prop value to match its allowed enum. See %s/strict-validation.",
            $matchCount,
            $exampleLines,
            WireKit::DOCS_URL,
        ));
    }
}
