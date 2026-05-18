<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use BaconQrCode\Renderer\ImageRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Verifies that WireKit is correctly integrated into the host application.
 *
 * Checks asset publishing, Blade directives, Tailwind @source, directive order,
 * and optional dependencies. Run after `composer install/update` or when
 * components look unstyled or non-interactive.
 *
 * Usage:
 *   php artisan wirekit:verify
 *
 * Returns exit code 1 on failure — can be used in CI or as a Claude Code hook.
 * Reference: docs/integration.md
 */
class VerifyInstallationCommand extends Command
{
    protected $signature = 'wirekit:verify';

    protected $description = 'Verify WireKit integration (assets, directives, Tailwind @source, optional deps)';

    private int $passed = 0;

    private int $warned = 0;

    private int $failed = 0;

    /** @var string[]|null Memoized layout file paths (used by multiple checks) */
    private ?array $layoutFiles = null;

    /** @var string[]|null Memoized all blade file paths */
    private ?array $allBladeFiles = null;

    public function handle(): int
    {
        $this->info('WireKit Integration Check');
        $this->line('');

        $this->checkPublishedAssets();
        $this->checkAssetFreshness();
        $this->checkTailwindSource();
        $this->checkConfigPublished();
        $this->checkBladeDirectives();
        $this->checkAlpineJs();
        $this->checkBundleConfig();
        $this->checkPublishedViewsStaleness();
        $this->checkFontAssets();
        $this->checkCssImportAntiPattern();
        $this->checkOptionalDependencies();
        $this->checkBuiltCssHasWireKitUtilities();
        $this->checkTokenAlignment();
        $this->checkRootDarkSymmetry();

        // ── Summary ──
        $this->line('');
        $this->line(sprintf(
            '  %s passed, %s warnings, %s failed',
            $this->passed,
            $this->warned,
            $this->failed
        ));

        if ($this->failed > 0) {
            $this->line('');
            $this->error('Integration incomplete — see failures above.');
            $this->line('  Reference: vendor/pushery/wirekit/docs/integration.md');

            return self::FAILURE;
        }

        if ($this->warned > 0) {
            $this->line('');
            $this->components->warn('Integration OK with warnings — consider fixing them.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->info('All checks passed.');

        return self::SUCCESS;
    }

    /**
     * Check that wirekit.css and wirekit.js are published to public/vendor/wirekit/.
     */
    private function checkPublishedAssets(): void
    {
        $vendorDir = public_path('vendor/wirekit');
        // The vendor directory exists but the JS/CSS files don't — strong signal
        // that the consumer ran `wirekit:install` once (which created the dir
        // and added it to .gitignore), then deployed without `vendor:publish
        // --force` in the post-deploy hook (or pulled with the dir gitignored
        // and the deploy stripped the contents). Different from the
        // never-installed case: the first-time-install fix is a single
        // `vendor:publish`; the missed-deploy-hook fix is wiring the publish
        // into every future deploy.
        $vendorDirExists = is_dir($vendorDir);

        $cssMissing = ! file_exists(public_path('vendor/wirekit/wirekit.css'));
        $jsMissing = ! file_exists(public_path('vendor/wirekit/wirekit.js'));

        if ($cssMissing) {
            $this->reportFail('wirekit.css not found in public/vendor/wirekit/');
        } else {
            $this->reportPass('wirekit.css published');
        }

        if ($jsMissing) {
            $this->reportFail('wirekit.js not found in public/vendor/wirekit/');
        } else {
            $this->reportPass('wirekit.js published');
        }

        // Only emit the consolidated fix hint once (not twice for css+js).
        if ($cssMissing || $jsMissing) {
            $this->line('  Fix: php artisan vendor:publish --tag=wirekit-assets --force');
            if ($vendorDirExists) {
                // Empty-but-existing directory — point at the deploy-hook scenario.
                $this->line('  Hint: public/vendor/wirekit/ exists but is empty.');
                $this->line('        Wire `vendor:publish --tag=wirekit-assets --force` into your post-deploy hook.');
                $this->line('        Default `wirekit:install` adds the dir to .gitignore, so deploys strip it.');
                $this->line('        See docs/integration.md "Deploy Checklist" for Forge / Envoyer / GitHub Actions snippets.');
            }
        }
    }

    /**
     * Compare MD5 hashes of published assets vs source files in the package.
     * Outdated assets cause subtle bugs (missing new CSS variables, stale JS).
     */
    private function checkAssetFreshness(): void
    {
        $this->checkFileFreshness(
            'wirekit.css',
            __DIR__.'/../../dist/wirekit.css',
            public_path('vendor/wirekit/wirekit.css')
        );

        $this->checkFileFreshness(
            'wirekit.js',
            __DIR__.'/../../dist/wirekit.js',
            public_path('vendor/wirekit/wirekit.js')
        );
    }

    private function checkFileFreshness(string $name, string $sourcePath, string $publishedPath): void
    {
        if (! file_exists($publishedPath) || ! file_exists($sourcePath)) {
            return; // Already reported as missing in checkPublishedAssets
        }

        if (md5_file($sourcePath) !== md5_file($publishedPath)) {
            $this->reportWarn("{$name} is outdated (source differs from published)");
            $this->line('  Fix: php artisan vendor:publish --tag=wirekit-assets --force');
        } else {
            $this->reportPass("{$name} is up to date");
        }
    }

    /**
     * Check that resources/css/app.css has a @source directive scanning WireKit Blade templates.
     * Without this, Tailwind v4 won't generate utility classes used by WireKit components.
     */
    private function checkTailwindSource(): void
    {
        $cssFiles = glob(resource_path('css/*.css')) ?: [];
        $hasSource = false;

        foreach ($cssFiles as $file) {
            $content = file_get_contents($file);
            if (str_contains($content, 'wirekit') && str_contains($content, '@source')) {
                $hasSource = true;

                break;
            }
        }

        if ($hasSource) {
            $this->reportPass('Tailwind @source includes WireKit templates');
        } else {
            $this->reportFail('Missing @source for WireKit in Tailwind CSS');
            $this->line('  Fix: Add to resources/css/app.css:');
            $this->line('  @source "../../vendor/pushery/wirekit/resources/views/**/*.blade.php";');
        }
    }

    /**
     * Check that config/wirekit.php has been published.
     * Not strictly required (mergeConfigFrom provides defaults), but recommended
     * so developers can customize fonts, icons, and chart adapters.
     */
    private function checkConfigPublished(): void
    {
        if (file_exists(config_path('wirekit.php'))) {
            $this->reportPass('config/wirekit.php published');
        } else {
            $this->reportWarn('config/wirekit.php not published (optional but recommended)');
            $this->line('  Fix: php artisan vendor:publish --tag=wirekit-config');
        }
    }

    /**
     * Check that @wirekitStyles and @wirekitScripts directives are present in layout files.
     * Also verifies directive ordering: @wirekitScripts must come before @livewireScripts
     * so Alpine.js component registrations are available when Livewire initializes.
     */
    private function checkBladeDirectives(): void
    {
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
            $rawContent = file_get_contents($file);
            // Strip Blade comments before scanning — otherwise a comment
            // containing the literal text `@livewireScripts` (e.g.
            // `{{-- Note: @livewireScripts must come AFTER @wirekitScripts --}}`)
            // makes strpos() return the comment's position, producing a
            // false-positive on the order check. Strip Blade comments
            // before scanning so an inline annotation referencing the
            // directive name doesn't mis-cue the order check.
            $content = preg_replace('/\{\{--.*?--\}\}/s', '', $rawContent) ?? $rawContent;

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
     * Detect whether resources/css/app.css imports wirekit.css via a CSS
     * `@import` rule. This is the alternative-but-equivalent setup path
     * to the `@wirekitStyles` Blade directive — see the integration docs
     * "Tip: Both setup paths work in v1.3.0+".
     */
    private function hasWirekitCssImportInAppCss(): bool
    {
        $appCss = resource_path('css/app.css');
        if (! file_exists($appCss)) {
            return false;
        }
        $content = file_get_contents($appCss);

        return (bool) preg_match('/@import\b[^;]*wirekit\.css/', $content);
    }

    /**
     * Check that Alpine.js is available in the application.
     * Without Alpine, all interactive WireKit components (modals, dropdowns, tooltips, etc.)
     * render as static HTML with no interactivity — the #1 reported issue.
     */
    private function checkAlpineJs(): void
    {
        // Livewire v4+ bundles Alpine.js — no separate import needed
        if ($this->detectLivewireVersion() >= 4) {
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
            $content = file_get_contents($file);
            if (preg_match('/alpinejs|alpine\.js/', $content)) {
                $hasAlpine = true;

                break;
            }
        }

        // Also check all blade files for CDN script tag
        if (! $hasAlpine) {
            foreach ($this->findAllBladeFiles() as $file) {
                $content = file_get_contents($file);
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

    /**
     * Validate that wirekit.scripts.bundle config value is valid.
     * A typo (e.g. "ful" instead of "full") causes a 404 on the JS asset.
     */
    private function checkBundleConfig(): void
    {
        $bundle = config('wirekit.scripts.bundle', 'full');
        $valid = ['full', 'core'];

        if (in_array($bundle, $valid, true)) {
            $this->reportPass("JS bundle configured: {$bundle}");
        } else {
            $this->reportFail("Invalid wirekit.scripts.bundle value: '{$bundle}'");
            $this->line('  Valid values: full, core');
        }
    }

    /**
     * Warn if WireKit views have been published (vendor override).
     * Published views override package views — after a WireKit update,
     * the published copies may be outdated and miss new features or fixes.
     */
    private function checkPublishedViewsStaleness(): void
    {
        $publishedViewsPath = resource_path('views/vendor/wirekit');

        if (! is_dir($publishedViewsPath)) {
            return; // Not published — nothing to check, this is the normal case
        }

        // Count published view files recursively (PHP glob() does not support **)
        $fileCount = count(File::allFiles($publishedViewsPath));

        if ($fileCount > 0) {
            $this->reportWarn("Published WireKit views detected ({$fileCount} files in views/vendor/wirekit/)");
            $this->line('  These override package views — after composer update they may be outdated');
            $this->line('  Fix: Delete resources/views/vendor/wirekit/ to use latest package views');
            $this->line('  Or re-publish: php artisan vendor:publish --tag=wirekit-views --force');
        }
    }

    /**
     * Check that font CSS files are published if custom fonts are configured.
     * When a font preset is set in config but the font files aren't published,
     * the GDPR-compliant self-hosted fonts won't load.
     */
    private function checkFontAssets(): void
    {
        $fontConfig = config('wirekit.fonts', []);
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
            $cssFiles = glob("{$fontDir}/*.css") ?: [];
            if ($cssFiles !== []) {
                $this->reportPass('Font assets published ('.count($cssFiles).' font CSS files)');
            } else {
                $this->reportWarn('Font directory exists but no CSS files found');
                $this->line('  Fix: php artisan vendor:publish --tag=wirekit-fonts --force');
            }
        } else {
            $this->reportWarn('Custom fonts configured but font assets not published');
            $this->line('  Fix: php artisan vendor:publish --tag=wirekit-fonts');
        }
    }

    /**
     * Report which CSS-loading path the consumer's app.css uses for wirekit.css.
     *
     * Both paths work as of v1.3.0 (the file ships with `:root {}` / `.dark {}`
     * blocks that resolve in any consumption context):
     *   1. @wirekitStyles Blade directive — emits a `<link>` tag (the
     *      "fastest" path, no Tailwind compile step required).
     *   2. @import from app.css — Tailwind v4 picks up the variables;
     *      slightly slower compile but useful when consumers want a single
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

        $content = file_get_contents($appCss);

        if (preg_match('/@import\b.*wirekit\.css/', $content)) {
            $this->reportPass('wirekit.css is @import-ed in app.css (valid setup path)');
        }
    }

    /**
     * Check optional dependencies: Chart.js adapter and QR Code package.
     * These are INFO-level only — not required for core functionality.
     */
    private function checkOptionalDependencies(): void
    {
        $chartConfig = config('wirekit.charts.library');

        if ($chartConfig === 'chartjs') {
            $this->reportPass('Chart.js adapter configured');
        } elseif ($chartConfig === 'apexcharts') {
            $this->checkApexChartsAdapter();
        } else {
            $this->line('  <fg=cyan>i</> Chart adapter not configured (optional — set charts.library to "chartjs" or "apexcharts" in config/wirekit.php to enable <x-wirekit-chart>)');
        }

        if (class_exists(ImageRenderer::class)) {
            $this->reportPass('bacon/bacon-qr-code installed');
        } else {
            $this->line('  <fg=cyan>i</> bacon/bacon-qr-code not installed (optional — only needed for <x-wirekit::qr-code>)');
        }
    }

    /**
     * Three-step ApexCharts adapter check:
     *   1. Confirm the apexcharts npm package is installed (FAIL on absence —
     *      otherwise the chart renders blank with a console.error).
     *   2. License-tier reminder — WARN when apex_license is unset / 'community';
     *      PASS when 'commercial' / 'oem'. Never FAIL purely on tier choice
     *      (license compliance is the consumer's responsibility, not a config
     *      error).
     *   3. Adapter-bundle presence — confirm dist/wirekit-apex.js was published
     *      to the public/vendor folder. WARN on absence with a republish hint.
     */
    private function checkApexChartsAdapter(): void
    {
        $this->reportPass('ApexCharts adapter configured');

        // Step 1: verify the apexcharts npm package is installed.
        $packageJsonPath = base_path('package.json');
        if (file_exists($packageJsonPath)) {
            $packageJson = json_decode((string) file_get_contents($packageJsonPath), true) ?: [];
            $deps = array_merge(
                $packageJson['dependencies'] ?? [],
                $packageJson['devDependencies'] ?? [],
            );
            if (! isset($deps['apexcharts'])) {
                $this->reportFail(
                    'apexcharts npm package not found in package.json. '
                    .'Install with `npm install apexcharts` and import it in resources/js/app.js: '
                    .'`import ApexCharts from "apexcharts"; window.ApexCharts = ApexCharts;`'
                );
            } else {
                $this->reportPass('apexcharts npm package installed');
            }
        } else {
            $this->line('  <fg=cyan>i</> package.json not found — skipping apexcharts npm presence check');
        }

        // Step 2: license-tier reminder. WARN-only; never FAIL on this.
        $tier = config('wirekit.charts.apex_license');
        if ($tier === 'commercial' || $tier === 'oem') {
            $this->reportPass(sprintf('ApexCharts license tier declared: %s', $tier));
        } else {
            $this->reportWarn(
                'ApexCharts is non-MIT. Confirm your organisation is below the '
                .'$2M USD revenue threshold for the Community License, or purchase a '
                .'Commercial License at https://apexcharts.com/license/. '
                .'Record your tier via `charts.apex_license` in config/wirekit.php '
                .'(values: community / commercial / oem) to silence this reminder.'
            );
        }

        // Step 3: adapter-bundle presence — wirekit-apex.js needs to be
        // accessible at the public asset path.
        $publishedAdapterBundle = public_path('vendor/wirekit/wirekit-apex.js');
        if (file_exists($publishedAdapterBundle)) {
            $this->reportPass('dist/wirekit-apex.js published to public/vendor/wirekit/');
        } else {
            $this->reportWarn(
                'dist/wirekit-apex.js not found at '.$publishedAdapterBundle.'. '
                .'Run `php artisan vendor:publish --tag=wirekit-assets --force` to publish '
                .'the ApexCharts adapter bundle alongside the main bundle.'
            );
        }
    }

    /**
     * Final post-build sanity: if a Vite manifest exists, the BUILT app CSS
     * should reference at least one WireKit token. Catches the silent-failure
     * mode where a consumer adds the @source line to app.css but forgets to
     * run `npm run build` — the source-side check would still pass while the
     * page renders without WireKit utilities.
     *
     * Skipped silently in environments without `public/build/manifest.json`
     * (dev / pre-build / package-test scenarios).
     */
    private function checkBuiltCssHasWireKitUtilities(): void
    {
        $manifestPath = public_path('build/manifest.json');
        if (! file_exists($manifestPath)) {
            // Dev mode / pre-build — silently skip. Other checks already
            // surface the source-side state.
            return;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($manifest)) {
            $this->reportWarn('Vite manifest at public/build/manifest.json is not valid JSON — skipping built-CSS check');

            return;
        }

        // Vite manifest shape varies across major versions. Walk the entire
        // structure and collect every value whose `file` ends in `.css`.
        $cssEntries = [];
        $walk = function ($node) use (&$walk, &$cssEntries) {
            if (! is_array($node)) {
                return;
            }
            if (isset($node['file']) && is_string($node['file']) && str_ends_with($node['file'], '.css')) {
                $cssEntries[] = $node['file'];
            }
            // Some manifests nest CSS files in a `css` array on a JS entry.
            if (isset($node['css']) && is_array($node['css'])) {
                foreach ($node['css'] as $css) {
                    if (is_string($css) && str_ends_with($css, '.css')) {
                        $cssEntries[] = $css;
                    }
                }
            }
            foreach ($node as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };
        $walk($manifest);

        if (empty($cssEntries)) {
            // No CSS in the build output at all — likely a JS-only consumer.
            // Not necessarily a problem; skip silently.
            return;
        }

        foreach (array_unique($cssEntries) as $cssEntry) {
            $cssPath = public_path('build/'.$cssEntry);
            if (! file_exists($cssPath)) {
                continue;
            }
            $css = (string) file_get_contents($cssPath);
            // Look for any --color-wk-* token reference. Aggressive minifiers
            // could rename CSS custom properties in theory, but Tailwind v4
            // preserves them; if even one is missing across every CSS bundle
            // we flag the rebuild.
            if (str_contains($css, '--color-wk-')) {
                $this->reportPass('Built app CSS contains WireKit utility rules');

                return;
            }
        }

        $this->reportFail('Built app CSS does not reference WireKit utilities');
        $this->line('    Hint: run `npm run build` after adding the @source line for WireKit templates to app.css.');
    }

    /**
     * Find Blade layout files in common locations.
     * Laravel apps use various conventions for layout placement.
     * Results are memoized because multiple checks need layout files.
     *
     * @return string[]
     */
    private function findLayoutFiles(): array
    {
        if ($this->layoutFiles !== null) {
            return $this->layoutFiles;
        }

        $paths = [
            // Traditional layout directory
            resource_path('views/layouts'),
            // Laravel 11+ component layout convention
            resource_path('views/components/layouts'),
            // Livewire layout directory
            resource_path('views/livewire/layouts'),
        ];

        $files = [];

        foreach ($paths as $path) {
            if (is_dir($path)) {
                $found = File::glob("{$path}/*.blade.php");
                $files = array_merge($files, $found);
            }
        }

        // Also check for single-file layout component
        $singleLayout = resource_path('views/components/layout.blade.php');
        if (file_exists($singleLayout)) {
            $files[] = $singleLayout;
        }

        $this->layoutFiles = $files;

        return $files;
    }

    /**
     * Find ALL Blade files in resources/views/ recursively.
     * Scans beyond layout directories to catch directives in any template.
     *
     * @return string[]
     */
    private function findAllBladeFiles(): array
    {
        if ($this->allBladeFiles !== null) {
            return $this->allBladeFiles;
        }

        $viewsPath = resource_path('views');

        if (! is_dir($viewsPath)) {
            $this->allBladeFiles = [];

            return [];
        }

        $this->allBladeFiles = collect(File::allFiles($viewsPath))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.blade.php'))
            ->map(fn ($file) => $file->getPathname())
            ->values()
            ->all();

        return $this->allBladeFiles;
    }

    /**
     * Detect Livewire major version from composer.lock.
     * Livewire v4+ bundles Alpine.js, so a separate Alpine check is unnecessary.
     *
     * Composer's `version` field uses several string shapes:
     *   - "4.1.0"      → 4   (plain SemVer)
     *   - "v4.1.0"     → 4   (v-prefixed — common from git tags)
     *   - "dev-main"   → 0   (branch alias — caller treats as "unknown major")
     *   - "4.x-dev"    → 4   (branch alias of a major line)
     *   - "4.1.0-RC1"  → 4   (pre-release)
     *
     * The previous implementation `(int) $version[0]` returned 0 for every
     * v-prefixed string because `(int)"v" === 0` — flagging Alpine as
     * missing on every install where Composer kept the v prefix in the lock
     * file. The regex below scans for the first integer run anywhere in the
     * version string, so all five shapes above resolve correctly.
     */
    public function detectLivewireVersion(?string $lockPath = null): int
    {
        $lockPath ??= base_path('composer.lock');

        if (! file_exists($lockPath)) {
            return 0;
        }

        $lock = json_decode(file_get_contents($lockPath), true);
        if (! is_array($lock)) {
            return 0;
        }

        foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $package) {
            if (($package['name'] ?? null) === 'livewire/livewire') {
                $version = (string) ($package['version'] ?? '');
                // Match the FIRST run of digits anywhere in the version string.
                // Handles "v4.1.0", "4.1.0", "4.x-dev", "4.1.0-RC1".
                if (preg_match('/(\d+)/', $version, $m)) {
                    return (int) $m[1];
                }

                return 0;
            }
        }

        return 0;
    }

    /**
     * Print a green ✓ check result and increment passed counter.
     * Named reportPass/reportFail/reportWarn to avoid collisions with
     * Illuminate\Console\Command's own fail() and warn() methods.
     */
    private function reportPass(string $message): void
    {
        $this->line("  <fg=green>✓</> {$message}");
        $this->passed++;
    }

    private function reportFail(string $message): void
    {
        $this->line("  <fg=red>✗</> {$message}");
        $this->failed++;
    }

    private function reportWarn(string $message): void
    {
        $this->line("  <fg=yellow>!</> {$message}");
        $this->warned++;
    }

    /**
     * Token-alignment diagnostic — compares Tailwind tokens against WireKit
     * tokens in `resources/css/app.css`. Closes the brief's "WireKit chrome
     * was Inter while copy was Instrument Sans" footgun by surfacing the
     * mismatch at install-time rather than letting it ship to production.
     *
     * Checks:
     *   --font-sans   ↔ --font-wk-sans
     *   --font-serif  ↔ --font-wk-serif
     *   --font-mono   ↔ --font-wk-mono
     *   --color-accent ↔ --color-wk-accent
     *   --color-accent-foreground ↔ --color-wk-accent-fg
     *   --radius      ↔ --radius-wk
     *   --shadow      ↔ --shadow-wk
     *
     * Skips any pair where either side is a `var(...)` reference (the consumer
     * is intentionally aliasing) or unset. Emits ✓ when families match, ⚠ when
     * they differ with actionable hint.
     */
    private function checkTokenAlignment(): void
    {
        $this->line('');
        $this->line('  Token alignment:');

        $appCss = resource_path('css/app.css');

        if (! file_exists($appCss)) {
            $this->reportWarn('  resources/css/app.css not found — skipping token-alignment checks');

            return;
        }

        $content = (string) file_get_contents($appCss);

        $checks = [
            ['Sans font', '--font-sans', '--font-wk-sans', 'php artisan wirekit:install --font=<key>'],
            ['Serif font', '--font-serif', '--font-wk-serif', 'php artisan wirekit:install --font-serif=<key>'],
            ['Mono font', '--font-mono', '--font-wk-mono', 'php artisan wirekit:install --font-mono=<key>'],
            ['Accent colour', '--color-accent', '--color-wk-accent', 'set --color-accent in @theme to match WireKit accent'],
            ['Accent foreground', '--color-accent-foreground', '--color-wk-accent-fg', 'set --color-accent-foreground in @theme'],
            ['Border radius', '--radius', '--radius-wk', 'set --radius in @theme to match --radius-wk'],
            ['Shadow', '--shadow', '--shadow-wk', 'set --shadow in @theme to match --shadow-wk'],
        ];

        foreach ($checks as [$label, $tw, $wk, $hint]) {
            $this->compareTokenPair($content, $label, $tw, $wk, $hint);
        }
    }

    /**
     * Compares one Tailwind token vs. WireKit token pair and reports outcome.
     */
    private function compareTokenPair(string $cssContent, string $label, string $twToken, string $wkToken, string $hint): void
    {
        $twValue = $this->extractTokenValue($cssContent, $twToken);
        $wkValue = $this->extractTokenValue($cssContent, $wkToken);

        // Skip if either token is unset
        if ($twValue === null || $wkValue === null) {
            $this->line("    <fg=blue>i</> {$label}: skipped (Tailwind-side token unset)");

            return;
        }

        // Skip if either side is a var(...) reference (intentional aliasing)
        if (str_contains($twValue, 'var(') || str_contains($wkValue, 'var(')) {
            $this->line("    <fg=blue>i</> {$label}: skipped (var(...) reference — intentional alias)");

            return;
        }

        $twNormalised = $this->normaliseTokenValue($twValue);
        $wkNormalised = $this->normaliseTokenValue($wkValue);

        if ($twNormalised === $wkNormalised) {
            $this->line("    <fg=green>✓</> {$label}: aligned ({$twNormalised})");
            $this->passed++;
        } else {
            $this->reportWarn("  {$label}: mismatch — Tailwind `{$twValue}` vs WireKit `{$wkValue}`. Fix: {$hint}");
        }
    }

    /**
     * Extracts the value of a CSS custom property from the content.
     *
     * Returns null if the token is not found (consumer hasn't set it).
     */
    private function extractTokenValue(string $cssContent, string $token): ?string
    {
        $pattern = '/'.preg_quote($token, '/').'\s*:\s*([^;\n]+)/';

        if (preg_match($pattern, $cssContent, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Normalises a token value for cross-comparison.
     *
     * For font families: extracts the first comma-separated token, lowercases,
     * trims quotes. So `'Inter', ui-sans-serif` and `"Inter", ui-sans` both
     * normalise to `inter`.
     *
     * For other values: lowercases + trims whitespace.
     */
    private function normaliseTokenValue(string $value): string
    {
        $first = trim(explode(',', $value)[0]);
        $first = trim($first, "'\"");

        return mb_strtolower($first);
    }

    /**
     * Detect `:root` ↔ `.dark` color-token override asymmetry.
     *
     * If a consumer overrides `--color-wk-accent` in `:root` but DOES NOT
     * provide a matching declaration in `.dark`, dark mode silently falls
     * back to WireKit's default. The existing checkTokenAlignment()
     * compares Tailwind↔WireKit pairs, not the consumer's own root vs
     * dark blocks — so this complementary check fills that gap.
     *
     * Restricts to `--color-wk-*` family. Font / radius / shadow / motion
     * tokens are typically theme-agnostic (same value in both modes), so
     * asymmetry there is not a bug. Reads `resources/css/app.css` only —
     * the source of truth for consumer overrides; the built bundle aggregates
     * Tailwind output with the consumer's source so reading the source is
     * cleaner.
     */
    private function checkRootDarkSymmetry(): void
    {
        $appCss = resource_path('css/app.css');
        if (! file_exists($appCss)) {
            return;
        }
        $content = file_get_contents($appCss);
        if ($content === false) {
            return;
        }

        $rootBlock = $this->extractCssBlock($content, ':root');
        $darkBlock = $this->extractCssBlock($content, '.dark');

        if ($rootBlock === '' || $darkBlock === '') {
            // No :root or no .dark — no asymmetry to report. The user
            // either has neither (clean default) or has only :root with
            // no dark intention (also fine — they're light-only).
            return;
        }

        $rootTokens = $this->parseColorTokens($rootBlock);
        $darkTokens = $this->parseColorTokens($darkBlock);

        if ($rootTokens === []) {
            return;
        }

        $asymmetric = array_diff_key($rootTokens, $darkTokens);

        if ($asymmetric === []) {
            $this->reportPass('Token symmetry: every overridden `--color-wk-*` token has a matching `.dark` declaration');

            return;
        }

        $this->reportWarn('Token symmetry: '.count($asymmetric).' colour token(s) overridden in `:root` but not in `.dark`');
        foreach (array_keys($asymmetric) as $token) {
            $this->line("    <fg=gray>•</> {$token}");
        }
        $this->line('    <fg=gray>Dark mode falls back to WireKit defaults for these tokens.</>');
        $this->line('    <fg=gray>Add matching declarations to your `.dark { … }` block.</>');
    }

    /**
     * Extract the body of a CSS rule like `:root { ... }` or `.dark { ... }`.
     * Returns the inner text (without the wrapping braces) or empty string.
     * Naive — assumes no rule nesting; consumer overrides almost never nest.
     */
    private function extractCssBlock(string $css, string $selector): string
    {
        $pos = strpos($css, $selector);
        if ($pos === false) {
            return '';
        }
        $brace = strpos($css, '{', $pos);
        if ($brace === false) {
            return '';
        }
        $depth = 1;
        $i = $brace + 1;
        $start = $i;
        $len = strlen($css);
        while ($i < $len && $depth > 0) {
            if ($css[$i] === '{') {
                $depth++;
            } elseif ($css[$i] === '}') {
                $depth--;
            }
            $i++;
        }

        return substr($css, $start, ($i - 1) - $start);
    }

    /**
     * Parse `--color-wk-*: value;` declarations from a CSS block body.
     * Returns ['--color-wk-name' => 'value', ...]. Comments stripped first.
     * Restricted to the color-wk family — font/radius/shadow/motion tokens
     * are theme-agnostic and don't need .dark counterparts.
     */
    private function parseColorTokens(string $block): array
    {
        $block = preg_replace('~/\*.*?\*/~s', '', $block) ?? '';
        $tokens = [];
        foreach (explode(';', $block) as $decl) {
            $decl = trim($decl);
            if ($decl === '' || ! str_starts_with($decl, '--color-wk-')) {
                continue;
            }
            [$name, $value] = array_pad(array_map('trim', explode(':', $decl, 2)), 2, '');
            if ($name !== '') {
                $tokens[$name] = $value;
            }
        }

        return $tokens;
    }
}
