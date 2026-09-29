<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console\Verify;

use Illuminate\Console\Command;

/**
 * The lines one `wirekit:verify` run prints, and the three totals under them.
 *
 * One report per run: Artisan resolves a command once per process and hands the same instance to
 * every call, so totals held on the command would add one run to the next.
 *
 * @internal
 */
final class VerifyReport
{
    public int $passed = 0;

    public int $warned = 0;

    public int $failed = 0;

    public function __construct(private readonly Command $command) {}

    /**
     * A green ✓, counted as a pass.
     */
    public function pass(string $message): void
    {
        $this->line("  <fg=green>✓</> {$message}");
        $this->passed++;
    }

    /**
     * A red ✗, counted as a failure.
     */
    public function fail(string $message): void
    {
        $this->line("  <fg=red>✗</> {$message}");
        $this->failed++;
    }

    /**
     * A yellow !, counted as a warning.
     */
    public function warn(string $message): void
    {
        $this->line("  <fg=yellow>!</> {$message}");
        $this->warned++;
    }

    /**
     * INFO tier — informational, expected on bare installs, NOT a problem.
     * Distinct from PASS (everything's fine) and WARN (something the
     * developer should look at). INFO is "this is the natural state of a
     * fresh install; here's the next step if you want to act on it."
     *
     * It counts toward nothing, not as a pass: most INFO lines say "skipped"
     * or "NOT measured", and counting them as passes would claim a result for
     * checks that never ran. Every INFO line goes through here or through
     * infoIndented(), so `passed` is the ✓ count, exactly.
     */
    public function info(string $message): void
    {
        $this->line("  <fg=blue>i</> {$message}");
    }

    /**
     * INFO tier, one level in — for a diagnostic printed underneath a
     * check's own heading rather than at the top level of the report, so the
     * token-alignment skips share the one info emitter without losing their
     * indentation.
     */
    public function infoIndented(string $message): void
    {
        $this->line("    <fg=blue>i</> {$message}");
    }

    public function line(string $message = '', ?string $style = null): void
    {
        $this->command->line($message, $style);
    }
}
