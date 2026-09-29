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
final class AlpineJsCheck extends VerifyCheck
{
    public function run(): void
    {
        $this->checkAlpineJs();
    }

    /**
     * Check that Alpine.js is available in the application.
     * Without Alpine, all interactive WireKit components (modals, dropdowns, tooltips, etc.)
     * render as static HTML with no interactivity — the #1 reported issue.
     */
    private function checkAlpineJs(): void
    {
        // Livewire v4+ bundles Alpine.js — no separate import needed
        if ($this->context->command->detectLivewireVersion() >= 4) {
            $this->reportPass('Alpine.js provided by Livewire v4+');

            return;
        }

        $hasAlpine = false;

        // Check JS entry files for Alpine import
        $jsFiles = array_merge(
            glob(resource_path('js/app.js')) ?: [],
            glob(resource_path('js/app.ts')) ?: [],
            glob(resource_path('js/bootstrap.js')) ?: [],
            glob(resource_path('js/bootstrap.ts')) ?: [],
        );

        foreach ($jsFiles as $file) {
            $content = @file_get_contents($file);

            if ($content === false) {
                continue;
            }

            if (preg_match('/alpinejs|alpine\.js/', $content)) {
                $hasAlpine = true;

                break;
            }
        }

        // Also check all blade files for CDN script tag
        if (! $hasAlpine) {
            foreach ($this->findAllBladeFiles() as $file) {
                $content = @file_get_contents($file);

                if ($content === false) {
                    continue;
                }

                if (str_contains($content, 'alpinejs') || str_contains($content, 'alpine.js') || str_contains($content, 'Alpine.start')) {
                    $hasAlpine = true;

                    break;
                }
            }
        }

        if ($hasAlpine) {
            $this->reportPass('Alpine.js detected');
        } else {
            $this->reportFail('Alpine.js not detected in JS entry files or layout');
            $this->line('  Fix: npm install alpinejs, then import and start in resources/js/app.js');
            $this->line('  Or add CDN: <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js"></script>');
        }
    }
}
