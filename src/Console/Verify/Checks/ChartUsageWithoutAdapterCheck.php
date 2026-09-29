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
final class ChartUsageWithoutAdapterCheck extends VerifyCheck
{
    public function run(): void
    {
        $this->checkChartUsageWithoutAdapter();
    }

    /**
     * Catches the first-run-chart-crash UX: a developer drops
     * `<x-wirekit-chart>` (or `<x-wirekit::chart-mixed>` / a sparkline)
     * into a fresh app but `config('wirekit.charts.library')` is still
     * the package default of `null`. Without this check, the symptom is
     * either a 500 (production) or a placeholder div (debug) — both
     * land on the developer with no upstream signal that the doctor
     * could have caught the misconfiguration. Emits a single WARN
     * naming the first file the chart-tag is referenced in.
     */
    private function checkChartUsageWithoutAdapter(): void
    {
        // Adapter already configured — nothing to surface.
        if (config('wirekit.charts.library') !== null) {
            return;
        }

        $offenders = [];
        foreach ($this->findAllBladeFiles() as $file) {
            $content = (string) file_get_contents($file);
            // Strip Blade comments first — same comment-leakage class
            // the directive-order check guards against. A docs page
            // describing the chart tag inside `{{-- ... --}}` should
            // not count as a real usage.
            $stripped = preg_replace('/\{\{--.*?--\}\}/s', '', $content) ?? $content;
            if (
                str_contains($stripped, '<x-wirekit-chart')
                || str_contains($stripped, '<x-wirekit::chart-mixed')
                || str_contains($stripped, '<x-wirekit::chart-spark')
            ) {
                $offenders[] = $file;
            }
        }

        if ($offenders === []) {
            return;
        }

        $first = $offenders[0];
        $count = count($offenders);
        $extraSuffix = $count > 1 ? sprintf(' (+%d more)', $count - 1) : '';

        $this->reportWarn('<x-wirekit-chart> used but charts.library is null'.$extraSuffix);
        $this->line("  First reference: {$first}");
        $this->line('  Fix: set `\'charts\' => [\'library\' => \'chartjs\']` in config/wirekit.php, then `npm install chart.js`.');
        $this->line('  In APP_DEBUG=true the chart renders a placeholder div instead of crashing the page.');
    }
}
