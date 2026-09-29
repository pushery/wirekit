<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console\Verify\Checks;

use Pushery\WireKit\Console\Verify\VerifyCheck;
use Pushery\WireKit\Fonts\FontCss;
use Pushery\WireKit\Fonts\FontRegistry;
use Pushery\WireKit\Support\DirectoryHash;

/**
 * One check `wirekit:verify` runs, in its package tier. The command decides the order and
 * prints the totals; this class decides what it reports.
 *
 * @internal
 */
final class FontAssetsCheck extends VerifyCheck
{
    public function run(): void
    {
        $this->checkFontAssets();
    }

    /**
     * Check that font CSS files are published if custom fonts are configured.
     * When a font preset is set in config but the font files aren't published,
     * the GDPR-compliant self-hosted fonts won't load.
     */
    private function checkFontAssets(): void
    {
        $fontConfig = config('wirekit.fonts', []);

        // Reported before the publish state, and deliberately not gated on a
        // font being configured: a typo here is corrected to `swap` while
        // serving, so it produces no error anywhere and no visible difference
        // from having asked for `swap` on purpose. The only way to learn the
        // value never took is to be told.
        $rawDisplay = $fontConfig['display'] ?? FontCss::DEFAULT;

        if (! is_string($rawDisplay) || ! in_array(strtolower(trim($rawDisplay)), FontCss::VALID, true)) {
            $shown = is_string($rawDisplay) ? $rawDisplay : gettype($rawDisplay);
            $this->reportWarn("wirekit.fonts.display is '{$shown}', which is not a font-display value — falling back to '".FontCss::DEFAULT."'");
            $this->line('  Valid: '.implode(', ', FontCss::VALID));
        }

        $hasCustomFont = false;

        foreach (['sans', 'serif', 'mono'] as $category) {
            if (! empty($fontConfig[$category])) {
                $hasCustomFont = true;

                break;
            }
        }

        if (! $hasCustomFont) {
            return; // Using system fonts — no font assets needed
        }

        $fontDir = public_path('vendor/wirekit/fonts');

        if (is_dir($fontDir)) {
            // Font CSS publishes into nested category/name subdirs
            // (fonts/<category>/<name>/<name>.css — vendor:publish copies
            // resources/fonts/ verbatim), so scan RECURSIVELY. A top-level
            // glob("{$fontDir}/*.css") finds nothing even when fonts ARE
            // correctly published, producing a false "no CSS files found" warning.
            $cssFiles = $this->findFontCssFiles($fontDir);
            if ($cssFiles !== []) {
                // Presence alone is not a pass: a directory of the previous
                // release's bytes satisfies it fully. Compare the published bytes
                // against the bundled ones, the same md5 check checkAssetFreshness
                // runs for wirekit.css / wirekit.js.
                //
                // Named before the byte compare, because a display mismatch also
                // shows up there — and "outdated" would send someone looking for
                // an upgrade they never missed. Same fix, precise cause.
                $mismatches = $this->fontDisplayMismatches();
                $mismatchedKeys = array_column($mismatches, 'key');

                if ($mismatches !== []) {
                    $configured = FontCss::display();
                    $named = implode(', ', array_map(
                        static fn (array $m): string => "{$m['key']} declares {$m['declared']}",
                        $mismatches,
                    ));
                    $this->reportWarn("Published fonts do not carry the configured font-display '{$configured}' ({$named})");
                    $this->line('  Cause: `vendor:publish` copies the files verbatim, so they keep the shipped default.');
                    $this->line('  Fix: php artisan wirekit:publish-fonts --force');
                }

                $stale = array_values(array_diff($this->staleFontFamilies(), $mismatchedKeys));

                if ($stale !== []) {
                    $this->reportWarn('Font assets are outdated — the bundled release differs from the published copy ('.implode(', ', $stale).')');
                    $this->line('  Fix: php artisan wirekit:publish-fonts --force');
                } elseif ($mismatches === []) {
                    $this->reportPass('Font assets published ('.count($cssFiles).' font CSS files)');
                }
            } elseif ($this->isPackageDefaultFontConfig($fontConfig)) {
                // Empty dir + the package default ('inter'): the system-ui
                // fallback works out of the box, so this is an INFO — the same
                // treatment as the dir-missing branch below, not a false WARN.
                $this->reportInfo("Default 'inter' sans font configured (system-ui fallback works out of the box)");
                $this->line('  To self-host: php artisan vendor:publish --tag=wirekit-fonts');
            } else {
                // A non-default font was requested but no CSS is present — it
                // genuinely won't render until published. Real warning.
                $this->reportWarn('Font directory exists but no CSS files found');
                $this->line('  Fix: php artisan vendor:publish --tag=wirekit-fonts --force');
            }
        } else {
            // Package-default font configuration ('sans' => 'inter' shipped
            // in config/wirekit.php) + no published assets is the natural
            // state of a fresh install — emit an INFO hint, not a WARN,
            // so the doctor summary doesn't read as if something failed.
            // Anything OTHER than the package default IS a real warning
            // (the developer asked for a non-default font but the assets
            // never got published, which means it won't render).
            if ($this->isPackageDefaultFontConfig($fontConfig)) {
                $this->reportInfo("Default 'inter' sans font configured (system-ui fallback works out of the box)");
                $this->line('  To self-host: php artisan vendor:publish --tag=wirekit-fonts');
            } else {
                $this->reportWarn('Custom fonts configured but font assets not published');
                $this->line('  Fix: php artisan vendor:publish --tag=wirekit-fonts');
            }
        }
    }

    /**
     * Configured font families whose PUBLISHED bytes differ from the bundled
     * release — the stale-after-`composer update` state the presence-only check
     * could not see. Mirrors checkAssetFreshness's md5 compare for the font tree.
     *
     * @return list<string>
     */
    private function staleFontFamilies(): array
    {
        $stale = [];

        foreach (['sans', 'serif', 'mono'] as $category) {
            $key = config("wirekit.fonts.{$category}");

            if ($key === null || $key === '') {
                continue;
            }

            $preset = FontRegistry::get((string) $key);

            if ($preset === null) {
                continue;
            }

            $relative = dirname($preset->cssFile);
            $source = dirname(__DIR__, 2).'/../../resources/fonts/'.$relative;
            $target = public_path('vendor/wirekit/fonts/'.$relative);

            // The transform is what `wirekit:publish-fonts` writes, not what the
            // package ships: a stylesheet gets `wirekit.fonts.display` substituted
            // on the way out. Comparing against the raw source instead would call
            // every non-default `font-display` permanently stale, and the fix it
            // printed would not change the answer.
            if (is_dir($target) && ! DirectoryHash::matches($source, $target, FontCss::publishTransform())) {
                $stale[] = $preset->key;
            }
        }

        return $stale;
    }

    /**
     * Configured families whose published stylesheet declares a different
     * `font-display` than the config asks for.
     *
     * This is the one path `wirekit.fonts.display` cannot reach on its own: a
     * plain `vendor:publish --tag=wirekit-fonts` is a framework-side file copy,
     * so those files keep the `swap` the package ships no matter what the config
     * says. Reporting it is what keeps the key a switch instead of a decoration
     * — the failure is otherwise completely silent, because nothing breaks when
     * a font loads with the wrong display, it just does not do what was asked.
     *
     * @return list<array{key: string, declared: string}>
     */
    private function fontDisplayMismatches(): array
    {
        $configured = FontCss::display();
        $mismatched = [];

        foreach (['sans', 'serif', 'mono'] as $category) {
            $key = config("wirekit.fonts.{$category}");

            if ($key === null || $key === '') {
                continue;
            }

            $preset = FontRegistry::get((string) $key);

            if ($preset === null) {
                continue;
            }

            $published = public_path($preset->publishedCssPath());

            if (! is_file($published)) {
                continue;
            }

            $declared = FontCss::declaredDisplays((string) file_get_contents($published));

            // A stylesheet declaring none is not a disagreement — there is no
            // promise to break. Only a value that is present and different is.
            if ($declared !== [] && $declared !== [$configured]) {
                $mismatched[] = ['key' => $preset->key, 'declared' => implode('/', $declared)];
            }
        }

        return $mismatched;
    }

    /**
     * Recursively collect every `.css` file under the published fonts dir.
     * Font CSS lands at fonts/<category>/<name>/<name>.css, so a shallow
     * glob("{$fontDir}/*.css") misses it — this iterator finds it at any depth.
     *
     * @return list<string>
     */
    private function findFontCssFiles(string $fontDir): array
    {
        $found = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($fontDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'css') {
                $found[] = $file->getPathname();
            }
        }

        return $found;
    }

    /**
     * Detect whether the font config matches the package-shipped default
     * (config/wirekit.php → 'sans' => 'inter', serif + mono null). When
     * true, the "assets not published" state is a natural bare-install
     * condition, not a warning.
     *
     * @param  array<string, mixed>  $fontConfig
     */
    private function isPackageDefaultFontConfig(array $fontConfig): bool
    {
        return ($fontConfig['sans'] ?? null) === 'inter'
            && empty($fontConfig['serif'] ?? null)
            && empty($fontConfig['mono'] ?? null);
    }
}
