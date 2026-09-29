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
final class AssetFreshnessCheck extends VerifyCheck
{
    public function run(): void
    {
        $this->checkAssetFreshness();
    }

    /**
     * Compare MD5 hashes of published assets vs source files in the package.
     * Outdated assets cause subtle bugs (missing new CSS variables, stale JS).
     */
    private function checkAssetFreshness(): void
    {
        // The ground set is derived from what the package ships, not listed here: a list
        // stops being true quietly the day a bundle is added and nothing requires the list
        // to grow with it. A stale bundle is a page running new markup from the new PHP on
        // the old JavaScript, which is precisely the state this command exists to name.
        //
        // And it is the state an upgrade guarantees rather than risks: `composer
        // update` does not touch `public/vendor/wirekit/`, that directory is normally
        // gitignored, and nothing refreshes it until a person remembers. CI publishes
        // fresh on every run, so CI is green over a condition it never has — the two
        // drift apart and the green confirms the developer in the wrong one.
        //
        // A file present in the package but never published stays the presence
        // check's business: checkFileFreshness() returns early when the published
        // copy is absent, so nothing is reported twice.
        $distDir = dirname(__DIR__, 2).'/../../dist';
        $sources = glob($distDir.'/*.{css,js}', GLOB_BRACE) ?: [];
        sort($sources);

        foreach ($sources as $source) {
            $name = basename($source);

            $this->checkFileFreshness($name, $source, public_path('vendor/wirekit/'.$name));
        }

        // The Liquid Glass extension, but ONLY where it has been installed —
        // checkFileFreshness stays silent when the published file is absent, so
        // an application that never opted in hears nothing about it.
        //
        // Its own command publishes it, not `vendor:publish`, and that is the
        // whole reason this check exists: the extension is COPIED into
        // public/, so a composer update refreshes vendor/ and leaves the served
        // copy untouched. The page then keeps rendering the old stylesheet
        // through any number of deploys, and nothing anywhere says so — which
        // is exactly how a corrected refraction shipped and stayed invisible.
        foreach (['wirekit-glass.css', 'wirekit-glass.js'] as $file) {
            $this->checkFileFreshness(
                $file,
                dirname(__DIR__, 2).'/../../resources/glass/'.$file,
                public_path('vendor/wirekit/glass/'.$file),
                'php artisan wirekit:glass install'
            );
        }
    }

    /**
     * Whether this run has already explained what a stale published asset costs.
     */
    private bool $assetDeliveryConsequenceShown = false;

    private function checkFileFreshness(string $name, string $sourcePath, string $publishedPath, ?string $fixCommand = null): void
    {
        if (! file_exists($publishedPath) || ! file_exists($sourcePath)) {
            return; // Already reported as missing in checkPublishedAssets
        }

        if (md5_file($sourcePath) !== md5_file($publishedPath)) {
            $this->reportWarn("{$name} is outdated (source differs from published)");
            $this->line('  Fix: '.($fixCommand ?? 'php artisan vendor:publish --tag=wirekit-assets --force'));

            // Said once per run rather than once per file, because the consequence is the
            // same for all of them and six copies of it would read as six problems.
            //
            // "Outdated" undersells what has happened. A stale copy does not get served —
            // `@wirekitStyles` / `@wirekitScripts` compare size and hash, decide the
            // published copy cannot be trusted, and emit the package ROUTE instead. Nothing
            // throws, nothing logs, and the page is correct, so the only symptom is that
            // every asset now travels through PHP: no year-long immutable Cache-Control, no
            // precompressed sibling, none of what a developer configured for
            // `public/vendor/`.
            //
            // This is easiest to walk into after a patch too small to notice. A release that
            // changes one byte per file leaves every artifact the same SIZE, so "nothing
            // changed" is the natural reading — and the hash still differs, which is exactly
            // why the check hashes.
            if (! $this->assetDeliveryConsequenceShown) {
                $this->assetDeliveryConsequenceShown = true;
                $this->line('  Until then WireKit serves this through its package route rather than from');
                $this->line('  public/vendor/, so the cache headers and precompression configured for that');
                $this->line('  directory do not apply. The page renders correctly either way.');
            }
        } else {
            $this->reportPass("{$name} is up to date");
        }
    }
}
