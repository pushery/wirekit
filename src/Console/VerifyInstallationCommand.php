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
        if (file_exists(public_path('vendor/wirekit/wirekit.css'))) {
            $this->reportPass('wirekit.css published');
        } else {
            $this->reportFail('wirekit.css not found in public/vendor/wirekit/');
            $this->line('  Fix: php artisan vendor:publish --tag=wirekit-assets');
        }

        if (file_exists(public_path('vendor/wirekit/wirekit.js'))) {
            $this->reportPass('wirekit.js published');
        } else {
            $this->reportFail('wirekit.js not found in public/vendor/wirekit/');
            $this->line('  Fix: php artisan vendor:publish --tag=wirekit-assets');
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

        foreach ($bladeFiles as $file) {
            $content = file_get_contents($file);

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
                    }
                }
            }
        }

        if ($foundStyles) {
            $this->reportPass('@wirekitStyles directive found');
        } else {
            $this->reportFail('@wirekitStyles not found in any Blade file');
            $this->line('  Fix: Add @wirekitStyles in <head> of your layout');
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
        } elseif ($foundScripts) {
            $this->reportPass('@wirekitScripts is before @livewireScripts (or no explicit @livewireScripts)');
        }
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
     * Detect the anti-pattern of @import-ing wirekit.css in app.css.
     * wirekit.css contains Tailwind v4 directives (@theme, @custom-variant)
     * that conflict with the Tailwind Vite plugin — use @wirekitStyles instead.
     */
    private function checkCssImportAntiPattern(): void
    {
        $appCss = resource_path('css/app.css');

        if (! file_exists($appCss)) {
            return; // No app.css — nothing to check
        }

        $content = file_get_contents($appCss);

        // Check for @import of wirekit.css (common mistake)
        if (preg_match('/@import\b.*wirekit\.css/', $content)) {
            $this->reportWarn('wirekit.css is @import-ed in app.css — this causes conflicts');
            $this->line('  Fix: Remove the @import and use @wirekitStyles Blade directive instead');
            $this->line('  See: docs/integration.md → "Warning: Do NOT @import wirekit.css"');
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
        } else {
            $this->line('  <fg=cyan>i</> Chart.js not configured (optional — only needed for <x-wirekit::chart>)');
        }

        if (class_exists(ImageRenderer::class)) {
            $this->reportPass('bacon/bacon-qr-code installed');
        } else {
            $this->line('  <fg=cyan>i</> bacon/bacon-qr-code not installed (optional — only needed for <x-wirekit::qr-code>)');
        }
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
     */
    private function detectLivewireVersion(): int
    {
        $lockPath = base_path('composer.lock');

        if (! file_exists($lockPath)) {
            return 0;
        }

        $lock = json_decode(file_get_contents($lockPath), true);

        foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $package) {
            if ($package['name'] === 'livewire/livewire') {
                return (int) ($package['version'][0] ?? 0);
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
}
