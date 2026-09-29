<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console\Verify\Checks;

use Pushery\WireKit\Console\Verify\VerifyCheck;
use Pushery\WireKit\Support\AppCss;

/**
 * One check `wirekit:verify` runs, in its package tier. The command decides the order and
 * prints the totals; this class decides what it reports.
 *
 * @internal
 */
final class CssImportAntiPatternCheck extends VerifyCheck
{
    public function run(): void
    {
        $this->checkCssImportAntiPattern();
    }

    /**
     * Report which CSS-loading path the developer's app.css uses for wirekit.css.
     *
     * Both paths work as of v1.3.0 (the file ships with `:root {}` / `.dark {}`
     * blocks that resolve in any consumption context):
     *   1. @wirekitStyles Blade directive — emits a `<link>` tag (the
     *      "fastest" path, no Tailwind compile step required).
     *   2. @import from app.css — Tailwind v4 picks up the variables;
     *      slightly slower compile but useful when developers want a single
     *      bundled CSS file from Vite.
     *
     * Pre-v1.3.0 versions used `@theme {}` which browsers skipped as an
     * unknown at-rule via the `<link>` path, breaking the documented setup.
     * That's now fixed; this check just emits an informational line.
     */
    private function checkCssImportAntiPattern(): void
    {
        $appCss = resource_path('css/app.css');

        if (! file_exists($appCss)) {
            return; // No app.css — nothing to check
        }

        // Strip CSS comments first — a commented "(not an @import of
        // wirekit.css)" note must not trip a false @import PASS.
        $content = AppCss::withoutComments((string) file_get_contents($appCss));

        if (preg_match('/@import\b.*wirekit\.css/', $content)) {
            $this->reportPass('wirekit.css is @import-ed in app.css (valid setup path)');
        }
    }
}
