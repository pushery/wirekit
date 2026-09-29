<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console\Verify\Checks;

use Pushery\WireKit\Console\Verify\VerifyCheck;
use Pushery\WireKit\Support\LayoutShells;
use Pushery\WireKit\WireKit;

/**
 * One check `wirekit:verify` runs, in its package tier. The command decides the order and
 * prints the totals; this class decides what it reports.
 *
 * @internal
 */
final class PageShellsLoadWireKitCheck extends VerifyCheck
{
    public function run(): void
    {
        $this->checkPageShellsLoadWireKit();
    }

    /**
     * Does every page shell the app and auth layouts render actually load WireKit?
     *
     * The check above answers "is the directive somewhere?", and a directive can be somewhere
     * and still reach no page. The Livewire starter kit is such a case: its
     * `layouts/app.blade.php` hands the page to `layouts/app/sidebar.blade.php`, keeps
     * `layouts/app/header.blade.php` beside it, and switching between the two is a one-word
     * edit. Wired into the sidebar alone, a project passes the check above on every page and
     * loses WireKit on all of them the day somebody makes that edit.
     *
     * A WARN rather than a FAIL: a directive pushed through a stack, a section a child view
     * fills, or a view composer is invisible to the walk, so "not found from here" is not
     * "absent".
     */
    private function checkPageShellsLoadWireKit(): void
    {
        // Where the check above found no directive at all, its FAIL already said everything —
        // repeating it once per shell would bury the one line that matters.
        if (! $this->context->stylesFoundAnywhere && ! $this->context->scriptsFoundAnywhere) {
            return;
        }

        $shells = LayoutShells::forApplication();
        $stylesCovered = ! $this->context->stylesFoundAnywhere || $this->hasWirekitCssImportInAppCss();
        $missing = [];
        $inspected = 0;

        foreach (array_filter([$shells->appLayout(), $shells->authLayout()]) as $layout) {
            $found = $shells->shellsOf($layout);

            foreach ($found['head'] as $file) {
                $inspected++;

                if (! $stylesCovered && ! $shells->carries($file, 'wirekitStyles')) {
                    $missing[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file).' — no @wirekitStyles in its <head>';
                }
            }

            foreach ($found['body'] as $file) {
                $inspected++;

                if ($this->context->scriptsFoundAnywhere && ! $shells->carries($file, 'wirekitScripts')) {
                    $missing[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file).' — no @wirekitScripts before its </body>';
                }
            }
        }

        // No layout it could follow: nothing to report either way, and a PASS would claim a
        // result for a check that never ran.
        if ($inspected === 0) {
            return;
        }

        if ($missing === []) {
            $this->reportPass('Every page shell your layouts render loads WireKit');

            return;
        }

        $this->reportWarn('A page shell your layouts render does not load WireKit');

        foreach (array_values(array_unique($missing)) as $line) {
            $this->line('  '.$line);
        }

        $this->line('  Fix: php artisan wirekit:install — it follows each layout to these files. Loading WireKit through a @stack or a section instead is fine; this check cannot see those.');
    }
}
