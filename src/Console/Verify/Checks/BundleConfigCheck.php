<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console\Verify\Checks;

use Pushery\WireKit\Console\Verify\VerifyCheck;

/**
 * One check `wirekit:verify` runs, in its package tier. The command decides the order and
 * prints the totals; this class decides what it reports.
 *
 * @internal
 */
final class BundleConfigCheck extends VerifyCheck
{
    public function run(): void
    {
        $this->checkBundleConfig();
    }

    /**
     * Validate that wirekit.scripts.bundle config value is valid.
     * A typo (e.g. "ful" instead of "full") causes a 404 on the JS asset.
     */
    private function checkBundleConfig(): void
    {
        $bundle = config('wirekit.scripts.bundle', 'full');

        // `csp` belongs here. It is a shipped bundle with its own dist file and its own
        // documented reason to exist — an Alpine build that needs no 'unsafe-eval' — and
        // this list did not know about it. So `wirekit:verify` reported the one correct
        // choice a CSP-constrained app can make as an invalid value, and advised taking
        // it back. A check that tells you to undo a correct setting is worse than no
        // check: it is confidently wrong, in the one place a developer goes when they are
        // already unsure.
        $valid = ['full', 'core', 'csp'];

        if (in_array($bundle, $valid, true)) {
            $this->reportPass("JS bundle configured: {$bundle}");
        } else {
            $this->reportFail("Invalid wirekit.scripts.bundle value: '{$bundle}'");
            $this->line('  Valid values: full, core, csp');
        }
    }
}
