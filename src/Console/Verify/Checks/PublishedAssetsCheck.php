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
final class PublishedAssetsCheck extends VerifyCheck
{
    public function run(): void
    {
        $this->checkPublishedAssets();
    }

    /**
     * Check that wirekit.min.css and wirekit.js are published to public/vendor/wirekit/.
     *
     * The CSS checked here is the MINIFIED twin, because that is the file `@wirekitStyles`
     * links. The readable `wirekit.css` is published too — it is the source of truth and
     * what a developer reads — but its presence says nothing about whether the page will
     * be styled. Checking the readable one and reporting PASS while the linked one was
     * absent is precisely the shape of green this command exists to prevent.
     */
    private function checkPublishedAssets(): void
    {
        $vendorDir = public_path('vendor/wirekit');
        // The vendor directory exists but the JS/CSS files don't — strong signal
        // that the developer ran `wirekit:install` once (which created the dir
        // and added it to .gitignore), then deployed without `vendor:publish
        // --force` in the post-deploy hook (or pulled with the dir gitignored
        // and the deploy stripped the contents). Different from the
        // never-installed case: the first-time-install fix is a single
        // `vendor:publish`; the missed-deploy-hook fix is wiring the publish
        // into every future deploy.
        $vendorDirExists = is_dir($vendorDir);

        $cssMissing = ! file_exists(public_path('vendor/wirekit/wirekit.min.css'));
        $jsMissing = ! file_exists(public_path('vendor/wirekit/wirekit.js'));

        // --fix self-heal. When the
        // developer runs `wirekit:verify --fix` right after a fresh
        // clone (the common case where `public/vendor/wirekit/` is
        // gitignored), proactively trigger the publish so the doctor
        // can re-verify instead of just printing the publish command.
        if (($cssMissing || $jsMissing) && $this->option('fix')) {
            $this->line('  <fg=yellow>--fix:</> Publishing wirekit-assets...');
            $this->call('vendor:publish', [
                '--tag' => 'wirekit-assets',
                '--force' => true,
            ]);
            // Re-test the asset paths after the publish — if they're
            // now present, treat the check as a pass.
            $cssMissing = ! file_exists(public_path('vendor/wirekit/wirekit.min.css'));
            $jsMissing = ! file_exists(public_path('vendor/wirekit/wirekit.js'));
        }

        if ($cssMissing) {
            $this->reportFail('wirekit.min.css not found in public/vendor/wirekit/');
        } else {
            $this->reportPass('wirekit.min.css published');
        }

        if ($jsMissing) {
            $this->reportFail('wirekit.js not found in public/vendor/wirekit/');
        } else {
            $this->reportPass('wirekit.js published');
        }

        // Only emit the consolidated fix hint once (not twice for css+js).
        if ($cssMissing || $jsMissing) {
            $this->line('  Fix: php artisan vendor:publish --tag=wirekit-assets --force');
            $this->line('  Or:  php artisan wirekit:verify --fix   (self-heal)');
            if ($vendorDirExists) {
                // Empty-but-existing directory — point at the deploy-hook scenario.
                $this->line('  Hint: public/vendor/wirekit/ exists but is empty.');
                $this->line('        Wire `vendor:publish --tag=wirekit-assets --force` into your post-deploy hook.');
                $this->line('        Default `wirekit:install` adds the dir to .gitignore, so deploys strip it.');
                $this->line('        See '.WireKit::DOCS_URL.'/getting-started/integration "Deploy Checklist" for Forge / Envoyer / GitHub Actions snippets.');
            }
        }
    }
}
