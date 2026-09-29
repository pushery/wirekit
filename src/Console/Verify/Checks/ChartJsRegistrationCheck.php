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
final class ChartJsRegistrationCheck extends VerifyCheck
{
    public function run(): void
    {
        $this->checkChartJsRegistration();
    }

    /**
     * The "config says chartjs but the JS bundle never registered it"
     * gap: developer flipped `charts.library` to `chartjs` AND ran
     * `npm install chart.js` BUT didn't add the
     * `Chart.register(...registerables)` line to `resources/js/app.js`.
     * The chart component renders + mounts; the Alpine adapter runs, and
     * no chart draws: with nothing assigning `window.Chart` each one shows a
     * "Chart.js is not loaded" notice and the browser console names the fix,
     * and with the global assigned but no controllers registered Chart.js
     * throws in the console.
     *
     * Doctor catches this UPSTREAM by scanning `resources/js/app.js`
     * for the canonical registration pattern. WARN with the actionable
     * snippet when:
     *   - `config('wirekit.charts.library') === 'chartjs'` (developer
     *     enabled the chartjs adapter)
     *   - `resources/js/app.js` exists
     *   - The file does NOT contain BOTH an `import ... chart.js` AND
     *     a `Chart.register(` call.
     *
     * Silently skip in every other scenario:
     *   - chartjs not selected → no JS bootstrap needed
     *   - resources/js/app.js missing → bare-install path (handled by
     *     other checks)
     *   - registration already present → developer has done the right
     *     thing; nothing to surface.
     */
    private function checkChartJsRegistration(): void
    {
        if (config('wirekit.charts.library') !== 'chartjs') {
            return;
        }

        $appJsPath = resource_path('js/app.js');
        if (! file_exists($appJsPath)) {
            return;
        }

        $contents = (string) file_get_contents($appJsPath);

        // Strip JS // comments before scanning so a "// Missing:
        // Chart.register(...)" hint comment doesn't false-pass the
        // detection. Block comments are left as-is (rare in the
        // register-snippet area; treating them naively would risk
        // stripping legitimate code inside a `/* */` block).
        $stripped = preg_replace('~//[^\n]*~', '', $contents) ?? $contents;

        // Match the canonical patterns the chart.js documentation
        // recommends. Be lenient on whitespace and quote style.
        $hasImport = (bool) preg_match('/import\s+.*\s+from\s+[\'"]chart\.js[\'"]/', $stripped);
        $hasRegister = str_contains($stripped, 'Chart.register(');

        if ($hasImport && $hasRegister) {
            return;
        }

        // The self-hosted UMD build is a second, equally valid way to provide
        // Chart.js, and it needs no registration at all: it sets `window.Chart`
        // and registers every controller itself. `Chart.register(...registerables)`
        // is required only for the tree-shaken ESM import.
        //
        // Without this branch the doctor would tell an application whose charts demonstrably
        // draw that no chart would draw: not noise but a misleading warning, for an
        // application doing exactly the supported thing.
        if ($this->providesChartUmdBuild()) {
            return;
        }

        $this->reportWarn('Chart.js adapter selected but resources/js/app.js is missing the registration snippet');
        $this->line('  Fix: add the following to resources/js/app.js, then `npm run build`:');
        $this->line('');
        $this->line('    import { Chart, registerables } from \'chart.js\';');
        $this->line('    Chart.register(...registerables);');
        $this->line('');
        $this->line('  Without this, no <x-wirekit-chart> draws: each shows a "Chart.js is not loaded" notice, or Chart.js');
        $this->line('  throws for an unregistered controller. See '.WireKit::DOCS_URL.'/getting-started/integration#optional-dependencies');
        $this->line('  for the full setup walkthrough.');
    }

    /**
     * Is Chart.js provided as the self-hosted UMD build?
     *
     * That build sets `window.Chart` and registers its own controllers, so the
     * ESM registration snippet is not merely optional there — it does not apply.
     * Looked for in the two places it can legitimately live: shipped under
     * `public/`, or referenced from a Blade layout (a CDN tag, or an asset()
     * call pointing at a vendored copy).
     *
     * Deliberately a shallow filename check rather than a parse. The question is
     * only "is there another route by which Chart reaches the page", and a false
     * NEGATIVE here just restores the old warning — while a false positive costs
     * a developer a hunt for a defect that does not exist.
     */
    private function providesChartUmdBuild(): bool
    {
        foreach (glob(public_path('vendor/**/chart*.js')) ?: [] as $path) {
            if (str_contains(basename($path), 'chart')) {
                return true;
            }
        }

        $layouts = glob(resource_path('views/**/*.blade.php')) ?: [];

        foreach (array_merge($layouts, glob(resource_path('views/*.blade.php')) ?: []) as $path) {
            $blade = (string) file_get_contents($path);

            if (preg_match('/chart(?:\.umd)?(?:\.min)?\.js/i', $blade) === 1) {
                return true;
            }
        }

        return false;
    }
}
