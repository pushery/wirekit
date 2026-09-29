<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console\Verify\Checks;

use Pushery\WireKit\Console\Verify\VerifyCheck;
use Pushery\WireKit\WireKit;

/**
 * One check `wirekit:verify` runs, in its package tier. The command decides the order and
 * prints the totals; this class decides what it reports.
 *
 * @internal
 */
final class ReplacingPersonalizationsCheck extends VerifyCheck
{
    public function run(): void
    {
        $this->checkReplacingPersonalizations();
    }

    /**
     * Name the personalized blocks the application now owns.
     *
     * `WireKit::personalize()` takes two value shapes for a block, and they differ
     * in one consequence nobody is told about:
     *
     *   'base' => 'inline-flex …'                        REPLACES the shipped block
     *   'base' => fn (string $vendor) => $vendor.' …'    EXTENDS it
     *
     * A replacement is a legitimate choice and often the right one. What it also
     * does is end the flow of later improvements to that block — permanently, and
     * without a word. The personalization keeps looking like a decision somebody
     * made, which it is; that it has since swallowed three upstream changes is
     * visible nowhere. An adopting application found this by reading the installed
     * package, not by being told.
     *
     * So this reports, and does not judge: a WARN that names the blocks, because
     * the same output on a deliberate replacement is the point — the developer
     * sees what they own and can decide again. It is not a FAIL, and it never
     * suggests removing the personalization; the fix line offers the closure form
     * for the case where the delta was all that was wanted.
     */
    private function checkReplacingPersonalizations(): void
    {
        $owned = [];

        foreach (WireKit::personalizedComponents() as $component) {
            foreach (WireKit::personalizationFor($component) as $block => $value) {
                // A closure receives the vendor default and returns its own delta,
                // so it keeps inheriting. Only a finished string severs the link.
                if (is_string($value)) {
                    $owned[] = $component.'.'.$block;
                }
            }
        }

        if ($owned === []) {
            return; // nothing personalized, or every block extends — the quiet case
        }

        $count = count($owned);

        $this->reportWarn(
            "{$count} personalized class ".($count === 1 ? 'block replaces' : 'blocks replace').
            ' the shipped one'
        );
        $this->line('  '.implode(', ', $owned));
        $this->line('  A replaced block stops receiving later WireKit changes to it — silently, and for good');
        $this->line("  Fix (only if you wanted a delta): 'block' => fn (string \$vendor) => \$vendor.' your-classes'");
    }
}
