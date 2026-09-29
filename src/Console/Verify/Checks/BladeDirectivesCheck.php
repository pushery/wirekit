<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console\Verify\Checks;

use Pushery\WireKit\Console\Verify\VerifyCheck;
use Pushery\WireKit\Support\BladeParser;
use Pushery\WireKit\WireKit;

/**
 * One check `wirekit:verify` runs, in its package tier. The command decides the order and
 * prints the totals; this class decides what it reports.
 *
 * @internal
 */
final class BladeDirectivesCheck extends VerifyCheck
{
    public function run(): void
    {
        $this->checkBladeDirectives();
    }

    /**
     * Check that @wirekitStyles and @wirekitScripts directives are present in layout files.
     * Also verifies directive ordering: @wirekitScripts must come before @livewireScripts
     * so Alpine.js component registrations are available when Livewire initializes.
     */
    private function checkBladeDirectives(): void
    {
        // Bare-install INFO: when no layout file exists at any canonical
        // install path AND no @import alternative is configured in
        // app.css, emit a single INFO hint and skip the directive scan.
        // The natural state right after `wirekit:install` on a fresh
        // Laravel skeleton is "layout not yet written" — emitting two
        // FAIL lines plus the "Built app CSS" FAIL reads as if the
        // install failed, when actually the developer's next step is
        // simply to write the layout. Subsumes the historical "No Blade
        // files found" WARN because the empty-views-dir case is a
        // strict subset of "no canonical layout".
        if (! $this->hasAnyLayoutFile() && ! $this->hasWirekitCssImportInAppCss()) {
            $this->reportInfo('No app layout yet — run `php artisan wirekit:install`: it creates `resources/views/layouts/app.blade.php` via Livewire\'s `livewire:layout` and injects @wirekitStyles + @wirekitScripts (before @livewireScripts). Or create it yourself with `php artisan livewire:layout`, then re-run install.');

            return;
        }

        $bladeFiles = $this->findAllBladeFiles();

        if ($bladeFiles === []) {
            $this->reportWarn('No Blade files found — cannot verify directives');
            $this->line('  Searched: resources/views/');

            return;
        }

        $foundStyles = false;
        $foundScripts = false;
        $orderOk = true;
        $orderFailedFile = null;

        foreach ($bladeFiles as $file) {
            $rawContent = (string) file_get_contents($file);
            // Scan only the text this file would actually EXECUTE. A comment
            // that happens to name a directive answers the check for it, which
            // is the same failure this scan already guarded against for Blade
            // comments and was still open for three other comment syntaxes.
            $content = BladeParser::liveText($rawContent);

            if (str_contains($content, '@wirekitStyles')) {
                $foundStyles = true;
            }

            if (str_contains($content, '@wirekitScripts')) {
                $foundScripts = true;

                // Check directive order in every file where both directives appear
                if (str_contains($content, '@livewireScripts')) {
                    $wirekitPos = strpos($content, '@wirekitScripts');
                    $livewirePos = strpos($content, '@livewireScripts');

                    if ($wirekitPos > $livewirePos) {
                        $orderOk = false;
                        $orderFailedFile ??= $file;
                    }
                }
            }
        }

        // Remembered for checkPageShellsLoadWireKit(), which only speaks where this check found a
        // directive somewhere: where it found none, the FAIL below has already said everything.
        $this->context->stylesFoundAnywhere = $foundStyles;
        $this->context->scriptsFoundAnywhere = $foundScripts;

        // The @wirekitStyles directive is one of two valid setup paths;
        // the OTHER valid path is `@import 'wirekit.css'` in app.css.
        // checkCssImportAntiPattern() detects the second path and reports
        // it as PASS. To avoid a contradictory FAIL/PASS pair on the same
        // install, only fail @wirekitStyles when neither path is present.
        $hasImportPath = $this->hasWirekitCssImportInAppCss();

        if ($foundStyles) {
            $this->reportPass('@wirekitStyles directive found');
        } elseif ($hasImportPath) {
            $this->reportPass('@wirekitStyles not used (covered by `@import wirekit.css` in app.css — valid alternative)');
        } else {
            $this->reportFail('@wirekitStyles not found in any Blade file');
            $this->line('  Fix: Add @wirekitStyles in <head> of your layout');
            $this->line('  Or: @import \'../../vendor/pushery/wirekit/dist/wirekit.css\' in resources/css/app.css');
        }

        if ($foundScripts) {
            $this->reportPass('@wirekitScripts directive found');
        } else {
            $this->reportFail('@wirekitScripts not found in any Blade file');
            $this->line('  Fix: Add @wirekitScripts in <body> of your layout');
        }

        if ($foundScripts && ! $orderOk) {
            $this->reportFail('@wirekitScripts must appear BEFORE @livewireScripts');
            $this->line('  Reason: WireKit Alpine components must register before Livewire starts Alpine');
            if ($orderFailedFile !== null) {
                $this->line('  Found in: '.str_replace(base_path().DIRECTORY_SEPARATOR, '', $orderFailedFile));
            }
        } elseif ($foundScripts) {
            $this->reportPass('@wirekitScripts is before @livewireScripts (or no explicit @livewireScripts)');
        }
    }

    /**
     * Mirror of InstallCommand::addBladeDirectives() layout-path detection.
     * Used by checkBladeDirectives() to distinguish "no layout file yet"
     * (INFO — bare install, next step is on the developer) from "layout
     * exists but directives missing" (FAIL — real misconfiguration).
     *
     * Returns true when EITHER a single conventional layout file exists
     * (`views/components/layout.blade.php`) OR any `.blade.php` file lives
     * inside one of the conventional layout DIRECTORIES (`views/layouts/`,
     * where Livewire 4 registers `layouts::`, or `views/components/layouts/`,
     * the Livewire 3 location).
     * The directory-scan flavor matches real-world projects that ship
     * multiple sibling layouts (`app.blade.php`, `guest.blade.php`, etc.).
     */
    private function hasAnyLayoutFile(): bool
    {
        $singleFile = resource_path('views/components/layout.blade.php');
        if (file_exists($singleFile)) {
            return true;
        }

        $layoutDirs = [
            resource_path('views/components/layouts'),
            resource_path('views/layouts'),
        ];

        foreach ($layoutDirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            $files = glob($dir.'/*.blade.php');
            if ($files !== false && count($files) > 0) {
                return true;
            }
        }

        return false;
    }
}
