<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use BaconQrCode\Renderer\ImageRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Pushery\WireKit\WireKit;

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
 * Reference: https://docs.wirekit.app/getting-started/integration
 */
class VerifyInstallationCommand extends Command
{
    protected $signature = 'wirekit:verify
        {--tier= : Filter to a single check tier — "package" (asset / config / directive checks for the WireKit install itself) or "environment" (Laravel-level state checks like compiled-view freshness). Default = run every check.}
        {--fix : proactively self-heal missing public/vendor/wirekit/* assets by triggering `vendor:publish --tag=wirekit-assets --force`. Useful right after a fresh clone (where the assets are .gitignored) to avoid a red doctor on first run.}';

    protected $description = 'Verify WireKit integration (assets, directives, Tailwind @source, optional deps)';

    /**
     * Register the `wirekit:doctor` alias on the SAME Symfony command
     * instance. v2.0.0 shipped a separate DoctorCommand subclass which
     * appeared as TWO entries in `php artisan list wirekit`; v2.1.0
     * collapses both names to one canonical entry — `php artisan list`
     * now shows `wirekit:verify` with `Aliases: wirekit:doctor` underneath,
     * matching the de-facto Laravel ecosystem norm for diagnostic
     * commands.
     *
     * Existing CI scripts and docs that reference `wirekit:doctor`
     * continue to work — Symfony Console routes alias invocations to
     * the canonical command without behavior change.
     */
    protected function configure(): void
    {
        parent::configure();
        $this->setAliases(['wirekit:doctor']);
    }

    private int $passed = 0;

    private int $warned = 0;

    private int $failed = 0;

    /** @var string[]|null Memoized layout file paths (used by multiple checks) */
    private ?array $layoutFiles = null;

    /** @var string[]|null Memoized all blade file paths */
    private ?array $allBladeFiles = null;

    public function handle(): int
    {
        $tier = $this->option('tier');
        if ($tier !== null && ! in_array($tier, ['package', 'environment'], true)) {
            $this->error("Unknown tier '{$tier}'. Available: package, environment.");

            return self::INVALID;
        }

        $this->info('WireKit Integration Check');
        $this->line('');

        // Package-tier checks — verify the WireKit install itself
        // (assets, config, directives, optional deps). These bite when
        // the package's own install / upgrade misfires.
        if ($tier === null || $tier === 'package') {
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
            $this->checkChartUsageWithoutAdapter();
            $this->checkChartJsRegistration();
            $this->checkBuiltCssHasWireKitUtilities();
            $this->checkTokenAlignment();
            $this->checkRootDarkSymmetry();
            $this->checkAlpinePluginCleanupHygiene();
        }

        // Environment-tier checks — verify the Laravel host environment
        // (compiled-view freshness, config-cache vs source drift, etc.).
        // These bite during interactive dev / CI even when the package
        // install is clean. Run `wirekit:doctor --tier=environment` to
        // get only these without the package-tier noise.
        if ($tier === null || $tier === 'environment') {
            $this->checkCompiledViewsFreshness();
            $this->checkSilentValidationTypos();
        }

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
            $this->line('  Reference: https://docs.wirekit.app/getting-started/integration');

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
        // that the developer ran `wirekit:install` once (which created the dir
        // and added it to .gitignore), then deployed without `vendor:publish
        // --force` in the post-deploy hook (or pulled with the dir gitignored
        // and the deploy stripped the contents). Different from the
        // never-installed case: the first-time-install fix is a single
        // `vendor:publish`; the missed-deploy-hook fix is wiring the publish
        // into every future deploy.
        $vendorDirExists = is_dir($vendorDir);

        $cssMissing = ! file_exists(public_path('vendor/wirekit/wirekit.css'));
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
            $cssMissing = ! file_exists(public_path('vendor/wirekit/wirekit.css'));
            $jsMissing = ! file_exists(public_path('vendor/wirekit/wirekit.js'));
        }

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
            $this->line('  Or:  php artisan wirekit:verify --fix   (self-heal)');
            if ($vendorDirExists) {
                // Empty-but-existing directory — point at the deploy-hook scenario.
                $this->line('  Hint: public/vendor/wirekit/ exists but is empty.');
                $this->line('        Wire `vendor:publish --tag=wirekit-assets --force` into your post-deploy hook.');
                $this->line('        Default `wirekit:install` adds the dir to .gitignore, so deploys strip it.');
                $this->line('        See https://docs.wirekit.app/getting-started/integration "Deploy Checklist" for Forge / Envoyer / GitHub Actions snippets.');
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
            $this->reportInfo('Layout file not yet created — `wirekit:install` injects @wirekitStyles + @wirekitScripts on a re-run once you add `resources/views/components/layouts/app.blade.php` (or `resources/views/layouts/app.blade.php`)');

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
     * Detect whether the font config matches the package-shipped default
     * (config/wirekit.php → 'sans' => 'inter', serif + mono null). When
     * true, the "assets not published" state is a natural bare-install
     * condition, not a warning.
     */
    private function isPackageDefaultFontConfig(array $fontConfig): bool
    {
        return ($fontConfig['sans'] ?? null) === 'inter'
            && empty($fontConfig['serif'] ?? null)
            && empty($fontConfig['mono'] ?? null);
    }

    /**
     * Mirror of InstallCommand::addBladeDirectives() layout-path detection.
     * Used by checkBladeDirectives() to distinguish "no layout file yet"
     * (INFO — bare install, next step is on the developer) from "layout
     * exists but directives missing" (FAIL — real misconfiguration).
     *
     * Returns true when EITHER a single conventional layout file exists
     * (`views/components/layout.blade.php`) OR any `.blade.php` file lives
     * inside one of the conventional layout DIRECTORIES (Laravel 12's
     * `views/components/layouts/` or the legacy `views/layouts/`).
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
     *      (license compliance is the developer's responsibility, not a config
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
                'ApexCharts is non-MIT. Confirm your organization is below the '
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

    /**
     * The "config says chartjs but the JS bundle never registered it"
     * gap: developer flipped `charts.library` to `chartjs` AND ran
     * `npm install chart.js` BUT didn't add the
     * `Chart.register(...registerables)` line to `resources/js/app.js`.
     * The chart component renders + mounts; the Alpine adapter runs;
     * Chart.js then prints a friendly "Chart.js is not loaded" error
     * to the browser console (chart.js:107) and silently fails to draw.
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

        $this->reportWarn('Chart.js adapter selected but resources/js/app.js is missing the registration snippet');
        $this->line('  Fix: add the following to resources/js/app.js, then `npm run build`:');
        $this->line('');
        $this->line('    import { Chart, registerables } from \'chart.js\';');
        $this->line('    Chart.register(...registerables);');
        $this->line('');
        $this->line('  Without this, every <x-wirekit-chart> renders but draws nothing — chart.js logs a friendly');
        $this->line('  console.error at runtime and gives up. See '.WireKit::DOCS_URL.'/getting-started/integration#optional-dependencies');
        $this->line('  for the full setup walkthrough.');
    }

    /**
     * Final post-build sanity: if a Vite manifest exists, the BUILT app CSS
     * should reference at least one WireKit token. Catches the silent-failure
     * mode where a developer adds the @source line to app.css but forgets to
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
            // No CSS in the build output at all — likely a JS-only developer.
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
     * INFO tier — informational, expected on bare installs, NOT a problem.
     * Distinct from PASS (everything's fine) and WARN (something the
     * developer should look at). INFO is "this is the natural state of a
     * fresh install; here's the next step if you want to act on it."
     * Counts as PASS in the summary tally so the summary line doesn't
     * read as if something failed.
     */
    private function reportInfo(string $message): void
    {
        $this->line("  <fg=blue>i</> {$message}");
        $this->passed++;
    }

    /**
     * Token-alignment diagnostic — compares Tailwind tokens against WireKit
     * tokens in `resources/css/app.css`. Surfaces the footgun where WireKit
     * chrome renders in a different font than the body copy at install-time,
     * rather than letting the mismatch ship to production.
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
     * Skips any pair where either side is a `var(...)` reference (the developer
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
            ['Accent color', '--color-accent', '--color-wk-accent', 'set --color-accent in @theme to match WireKit accent'],
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

        $twNormalised = $this->normalizeTokenValue($twValue);
        $wkNormalised = $this->normalizeTokenValue($wkValue);

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
     * Returns null if the token is not found (developer hasn't set it).
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
     * Normalizes a token value for cross-comparison.
     *
     * For font families: extracts the first comma-separated token, lowercases,
     * trims quotes. So `'Inter', ui-sans-serif` and `"Inter", ui-sans` both
     * normalize to `inter`.
     *
     * For other values: lowercases + trims whitespace.
     */
    private function normalizeTokenValue(string $value): string
    {
        $first = trim(explode(',', $value)[0]);
        $first = trim($first, "'\"");

        return mb_strtolower($first);
    }

    /**
     * Detect `:root` ↔ `.dark` color-token override asymmetry.
     *
     * If a developer overrides `--color-wk-accent` in `:root` but DOES NOT
     * provide a matching declaration in `.dark`, dark mode silently falls
     * back to WireKit's default. The existing checkTokenAlignment()
     * compares Tailwind↔WireKit pairs, not the developer's own root vs
     * dark blocks — so this complementary check fills that gap.
     *
     * Restricts to `--color-wk-*` family. Font / radius / shadow / motion
     * tokens are typically theme-agnostic (same value in both modes), so
     * asymmetry there is not a bug. Reads `resources/css/app.css` only —
     * the source of truth for developer overrides; the built bundle aggregates
     * Tailwind output with the developer's source so reading the source is
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

        $this->reportWarn('Token symmetry: '.count($asymmetric).' color token(s) overridden in `:root` but not in `.dark`');
        foreach (array_keys($asymmetric) as $token) {
            $this->line("    <fg=gray>•</> {$token}");
        }
        $this->line('    <fg=gray>Dark mode falls back to WireKit defaults for these tokens.</>');
        $this->line('    <fg=gray>Add matching declarations to your `.dark { … }` block.</>');
    }

    /**
     * Static analysis for Alpine-plugin defensive-cleanup hygiene.
     *
     * Scans the developer's `resources/js/` tree (the canonical location for
     * custom Alpine plugins extending WireKit) and flags two anti-patterns
     * that historically pollute developer browser-test console-error
     * assertions:
     *
     *  - **Observer instantiation WITHOUT a `destroy()` cleanup hook.** A
     *    `new IntersectionObserver(...)` / `new MutationObserver(...)` /
     *    `new ResizeObserver(...)` stored on `this` survives the Alpine
     *    instance's GC eligibility because the observer holds a reference
     *    to the host element. Memory leak + future-callback timing
     *    surface. Without `destroy()` the observer is never disconnected.
     *
     *  - **`disconnect()` call inside an observer callback WITHOUT a
     *    null-guard on the observer reference.** Browser-queued callbacks
     *    can execute AFTER Alpine teardown set `this._observer = null`
     *    (Livewire morph removing the host element pre-intersection is
     *    the canonical trigger). Without the guard, the callback throws
     *    `TypeError: Cannot read properties of null` — the bug class
     *    that WireKit's own `wirekitStatAnimate` / `wirekitAnimate`
     *    plugins shipped in earlier versions and patched in v2.0.0.
     *
     * Heuristic — not a perfect AST analysis, but covers the canonical
     * shape WireKit's own plugins follow. Edge cases (callback bound via
     * `.bind(this)`, observer reference held under a different name like
     * `this._intersectionObserver`) emit a soft WARN with a
     * docs-cross-link instead of a hard FAIL so developers can opt out
     * with a `// wirekit-doctor: cleanup-ok` comment when their pattern
     * is intentionally different.
     */
    private function checkAlpinePluginCleanupHygiene(): void
    {
        $developerJsDir = resource_path('js');
        if (! is_dir($developerJsDir)) {
            // Developers without a `resources/js/` tree have nothing for
            // this check to walk — but the doctor's contract is "every
            // check emits at least one observable line", so emit an INFO
            // here instead of returning silently. Tests asserting the
            // check ran (e.g. DoctorAliasTest "still runs and produces
            // structurally identical output") then see the marker
            // regardless of worker-sandbox state.
            $this->reportInfo('Alpine plugin cleanup hygiene: skipped — no resources/js/ directory (no developer-side Alpine plugins to scan)');

            return;
        }

        // Defensive iterator wrapping — if the directory exists but the
        // recursive walk throws (race-deleted sub-tree, permission flip
        // during parallel test runs, exotic filesystem error), surface
        // the issue as an INFO and degrade. Without this, an unhandled
        // throw aborts the whole doctor handle() pipeline mid-flight.
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($developerJsDir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
        } catch (\Throwable $e) {
            $this->reportInfo('Alpine plugin cleanup hygiene: scan skipped — '.$e::class.': '.mb_substr($e->getMessage(), 0, 100));

            return;
        }

        $issues = [];

        // Defensive iteration — wrap the foreach so a mid-walk throw
        // (filesystem race, vanished sub-directory) degrades to an INFO
        // emission instead of aborting the doctor's check pipeline.
        try {
            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'js') {
                    continue;
                }
                $path = $file->getPathname();
                $source = @file_get_contents($path);
                if ($source === false || $source === '') {
                    continue;
                }

                // Opt-out comment lets developers acknowledge intentional patterns.
                if (str_contains($source, '// wirekit-doctor: cleanup-ok')) {
                    continue;
                }

                $relativePath = str_replace($developerJsDir.'/', '', $path);

                // Anti-pattern 1: observer instantiation without destroy()
                $hasObserver = preg_match(
                    '/new\s+(?:IntersectionObserver|MutationObserver|ResizeObserver)\s*\(/',
                    $source
                ) === 1;
                $hasDestroy = (
                    preg_match('/\bdestroy\s*\(\s*\)\s*\{/', $source) === 1
                    || preg_match('/\bdestroy\s*:\s*(?:function\s*)?\(/', $source) === 1
                );

                if ($hasObserver && ! $hasDestroy) {
                    $issues[$relativePath][] = 'observer-without-destroy';
                }

                // Anti-pattern 2: disconnect() inside an observer callback
                // without a preceding null-guard. We detect any
                // `.disconnect()` call AND the absence of either:
                //   - `if (! this._<obs>) return;` somewhere in the same file
                //   - `this._<obs>?.disconnect()` optional-chaining form
                $hasDisconnect = preg_match('/\.disconnect\s*\(\s*\)/', $source) === 1;
                $hasGuard = (
                    preg_match('/if\s*\(\s*!\s*this\._\w*[Oo]bserver\s*\)/', $source) === 1
                    || preg_match('/this\._\w*[Oo]bserver\?\.disconnect\s*\(/', $source) === 1
                );

                if ($hasDisconnect && $hasObserver && ! $hasGuard) {
                    $issues[$relativePath][] = 'disconnect-without-null-guard';
                }
            }
        } catch (\Throwable $e) {
            $this->reportInfo('Alpine plugin cleanup hygiene: scan partial — '.$e::class.': '.mb_substr($e->getMessage(), 0, 100));

            return;
        }

        if ($issues === []) {
            $this->reportPass('Alpine plugin cleanup hygiene: no developer-side observer-leak or null-guard anti-patterns detected');

            return;
        }

        $this->reportWarn('Alpine plugin cleanup hygiene: '.count($issues).' file(s) with potential anti-patterns');
        foreach ($issues as $relativePath => $detected) {
            $this->line("    <fg=gray>•</> {$relativePath}: ".implode(', ', $detected));
        }
        $this->line('    <fg=gray>See: https://docs.wirekit.app/extending/authoring-custom-alpine-plugins</>');
        $this->line('    <fg=gray>Opt out per-file with a `// wirekit-doctor: cleanup-ok` comment if intentional.</>');
    }

    /**
     * Extract the body of a CSS rule like `:root { ... }` or `.dark { ... }`.
     * Returns the inner text (without the wrapping braces) or empty string.
     * Naive — assumes no rule nesting; developer overrides almost never nest.
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

    /**
     * Detects compiled-view staleness — the canonical reason a developer
     * test sees "the new prop isn't there" even after their Blade source
     * carries it. Laravel's `storage/framework/views/` retains pre-edit
     * compiled templates whose filemtime granularity (1-second) AND
     * filesystem-cache lag can let stale output survive a fast file-edit
     * cycle. The first diagnostic chain a developer walks is "did I wire
     * the prop?" — this check short-circuits that and points at
     * `php artisan view:clear`.
     *
     * Threshold: 60-second buffer between newest source mtime and
     * newest compiled-view mtime. Below the threshold = no warning
     * (normal fast-edit window). Above = WARN with the actionable hint.
     *
     * False-positive mitigation: this is WARN (not FAIL), the recommended
     * action is non-destructive, and slow filesystems (NFS / Docker on
     * macOS) get the same advice they'd give themselves anyway. The
     * threshold is tuned to bite on "I edited an hour ago and the test
     * still fails" — not on "I just hit save".
     */
    private function checkCompiledViewsFreshness(): void
    {
        $compiledDir = storage_path('framework/views');
        $sourceDir = resource_path('views');

        // No compiled views = fresh state (Laravel will compile on the
        // next render). Silent skip — nothing meaningful to report.
        if (! is_dir($compiledDir) || ! is_dir($sourceDir)) {
            return;
        }

        $newestCompiled = $this->newestMtimeUnder($compiledDir);
        if ($newestCompiled === 0) {
            // Compiled directory exists but is empty. Same fresh-state semantics.
            return;
        }

        $newestSource = $this->newestMtimeUnder($sourceDir, ['php']);
        if ($newestSource === 0) {
            return;
        }

        $lagSeconds = $newestSource - $newestCompiled;
        $thresholdSeconds = 60;

        if ($lagSeconds < $thresholdSeconds) {
            $this->reportPass('Compiled views are fresh (no staleness detected)');

            return;
        }

        $this->reportWarn(sprintf(
            'Compiled views may be stale (resources/views/ has files newer than storage/framework/views/ by %s).',
            $this->humanDuration($lagSeconds)
        ));
        $this->line('  Run: php artisan view:clear');
        $this->line('  This is the canonical fix when a developer test asserts a Blade prop / class that');
        $this->line('  was just wired in source but the assertion still fails — the compiled-view cache');
        $this->line('  retained the pre-edit template.');
    }

    /**
     * Recursive newest-mtime scanner with an optional extension filter.
     * Used by checkCompiledViewsFreshness() for both the source and
     * compiled directory traversals.
     *
     * @param  list<string>  $extensionsAllowlist  Empty = every file qualifies.
     */
    private function newestMtimeUnder(string $dir, array $extensionsAllowlist = []): int
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $newest = 0;
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            if ($extensionsAllowlist !== [] && ! in_array($file->getExtension(), $extensionsAllowlist, true)) {
                continue;
            }
            $mtime = $file->getMTime();
            if ($mtime > $newest) {
                $newest = $mtime;
            }
        }

        return $newest;
    }

    /**
     * Pretty-print a duration in seconds as "Xh Ym" / "Xm Ys" / "Xs".
     * Used by the compiled-views-staleness check to produce the
     * actionable lag-amount in the WARN message.
     */
    private function humanDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.'s';
        }
        if ($seconds < 3600) {
            $minutes = (int) floor($seconds / 60);
            $remainder = $seconds % 60;

            return $remainder > 0 ? "{$minutes}m {$remainder}s" : "{$minutes}m";
        }
        $hours = (int) floor($seconds / 3600);
        $minutes = (int) floor(($seconds % 3600) / 60);

        return $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h";
    }

    /**
     * v2.4.0 Extension 3 — surface silent prop typos detected in the
     * Laravel log. The StrictnessGate emits `local.ERROR: WireKit [...]`
     * lines in HTTP dev mode when a developer passes an invalid prop
     * value (the gate logs + renders the fallback instead of throwing).
     * Without this scan a typo can sit in production code for weeks —
     * the page renders with the fallback and the developer never sees
     * the log.
     *
     * SAFE-DEGRADE contract — this check is an OPTIONAL helper, never
     * required, never blocks. Every branch handles its failure mode
     * gracefully:
     *   - Log file missing → INFO ("scan skipped — log not at default path")
     *   - Log file unreadable → INFO ("scan skipped — permission denied")
     *   - Log level filters below WARNING → INFO ("scan saw nothing —
     *     LOG_LEVEL may filter strict-validation writes")
     *   - Custom log channel (Slack, daily, stack-with-others) → INFO
     *     ("scan skipped — single-file scan can't see custom channels")
     *   - Found WireKit lines → WARN with example count + first 3 lines
     *   - Found none → PASS
     * The check NEVER returns FAIL — the doctor's overall exit code is
     * unaffected.
     *
     * Opt-out: set `wirekit.doctor.scan_logs` to `false` in config OR
     * pass `--no-scan-logs` flag on the command (registered separately).
     */
    private function checkSilentValidationTypos(): void
    {
        // SAFE-DEGRADE outer guard — wrap the entire method body in a
        // try/catch so ANY unexpected error (filesystem race during
        // parallel test runs, transient permission issue, glob() / fopen()
        // edge case) silently degrades to an INFO line instead of aborting
        // the rest of the doctor's check pipeline. The doctor's contract
        // (see method docblock) is that this check NEVER blocks; we extend
        // the contract here to "NEVER aborts subsequent checks either".
        // Without this guard, a transient PHP warning escalated to
        // exception under Pest's strict error handling would abort
        // `handle()` mid-pipeline and leak as a flaky test ("Alpine plugin
        // cleanup hygiene" missing because the check never ran).
        try {
            $this->checkSilentValidationTyposBody();
        } catch (\Throwable $e) {
            $this->reportInfo(
                'silent-typo log scan skipped — transient I/O error during scan ('.
                $e::class.': '.mb_substr($e->getMessage(), 0, 100).'). '.
                'The scan is an optional helper; subsequent doctor checks are unaffected.'
            );
        }
    }

    private function checkSilentValidationTyposBody(): void
    {
        // Honor opt-out config — defaults to true (helper IS on by default
        // in dev environments; production developers may disable explicitly).
        $enabled = (bool) config('wirekit.doctor.scan_logs', true);
        if (! $enabled) {
            return;
        }

        // Custom log channels — when the developer routes logs anywhere
        // OTHER than the default single-file channel ('single' or 'daily'),
        // scanning a static file path would miss the writes entirely. Bail
        // out with an INFO instead of falsely reporting "no typos".
        $defaultChannel = (string) config('logging.default', 'stack');
        $stackChannels = (array) config('logging.channels.stack.channels', ['single']);
        $scannableChannels = ['single', 'daily', 'stack'];
        if (! in_array($defaultChannel, $scannableChannels, true)) {
            $this->reportInfo(sprintf(
                'silent-typo log scan skipped — LOG_CHANNEL=%s routes elsewhere (Slack / Papertrail / Sentry / custom). '.
                'Inspect that destination for `WireKit [...]` ERROR/WARNING lines manually.',
                $defaultChannel
            ));

            return;
        }
        if ($defaultChannel === 'stack') {
            $usableInStack = array_intersect($stackChannels, ['single', 'daily']);
            if ($usableInStack === []) {
                $this->reportInfo(
                    'silent-typo log scan skipped — LOG_STACK contains only non-file channels. '.
                    'Inspect the configured destinations for `WireKit [...]` ERROR/WARNING lines manually.'
                );

                return;
            }
        }

        // Resolve the log file path — Laravel's default 'single' channel
        // writes to storage/logs/laravel.log. The 'daily' channel rotates
        // per day (laravel-YYYY-MM-DD.log); scan today's file.
        $logDir = storage_path('logs');
        if (! is_dir($logDir)) {
            $this->reportInfo(
                'silent-typo log scan skipped — storage/logs directory missing (fresh install? skipped log writes?).'
            );

            return;
        }

        $logFiles = [];
        $singlePath = $logDir.'/laravel.log';
        if (is_file($singlePath) && is_readable($singlePath)) {
            $logFiles[] = $singlePath;
        }
        $dailyPattern = $logDir.'/laravel-*.log';
        $dailyFiles = glob($dailyPattern) ?: [];
        // Sort by mtime — race-safe via @suppression so a sibling worker
        // deleting a daily-rotated log between glob() and filemtime()
        // doesn't escalate a warning to a fatal under Pest's strict
        // error handling. Missing mtime sorts to 0 (treated as oldest).
        usort($dailyFiles, fn ($a, $b) => ((int) @filemtime($a)) <=> ((int) @filemtime($b)));
        foreach (array_slice($dailyFiles, -3) as $daily) {
            // Re-verify readability AT read-time (the file may have
            // vanished between glob() and now under parallel test runs).
            if (is_file($daily) && is_readable($daily)) {
                $logFiles[] = $daily;
            }
        }

        if ($logFiles === []) {
            $this->reportInfo(
                'silent-typo log scan skipped — no readable log files found at storage/logs/laravel*.log. '.
                'If your app writes logs elsewhere, this check is a no-op (expected behavior).'
            );

            return;
        }

        // Scan each log file for `local.ERROR: WireKit [...]` or
        // `local.WARNING: WireKit [...]` lines. We use a streaming
        // scanner — read line-by-line — so a huge log file doesn't
        // exhaust memory.
        $matches = [];
        $matchCount = 0;
        $matchPattern = '/^\[[^\]]+\] [a-z]+\.(ERROR|WARNING): WireKit \[/i';

        foreach ($logFiles as $path) {
            $fh = @fopen($path, 'r');
            if ($fh === false) {
                continue;
            }
            while (($line = fgets($fh)) !== false) {
                if (preg_match($matchPattern, $line)) {
                    $matchCount++;
                    if (count($matches) < 3) {
                        $matches[] = trim($line);
                    }
                }
            }
            fclose($fh);
        }

        if ($matchCount === 0) {
            $this->reportPass(sprintf(
                'silent-typo log scan clean — no `WireKit [...]` ERROR/WARNING lines in %d log file(s). '.
                '(If your env logs at level=critical/emergency only, fallback warnings get filtered out — that is expected.)',
                count($logFiles)
            ));

            return;
        }

        // Found something — surface as WARN, NEVER as FAIL. The doctor
        // shouldn't refuse to exit zero just because the developer left
        // a typo in a button. Show count + first 3 example lines.
        $exampleLines = implode("\n      ", array_map(
            fn ($l) => mb_substr($l, 0, 180).(mb_strlen($l) > 180 ? '…' : ''),
            $matches
        ));
        $this->reportWarn(sprintf(
            "silent prop-typo signals found in storage/logs: %d `WireKit [...]` ERROR/WARNING line(s). Examples:\n      %s\n      Fix each component prop value to match its allowed enum. See https://docs.wirekit.app/strict-validation.",
            $matchCount,
            $exampleLines
        ));
    }
}
