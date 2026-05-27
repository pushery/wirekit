<?php

declare(strict_types=1);

namespace Pushery\WireKit;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use Pushery\WireKit\Charts\ChartManager;
use Pushery\WireKit\Components\Chart;
use Pushery\WireKit\Console\ClassByAreaCommand;
use Pushery\WireKit\Console\ComponentMakeCommand;
use Pushery\WireKit\Console\CursorRulesCommand;
use Pushery\WireKit\Console\DoctorA11yCommand;
use Pushery\WireKit\Console\ExportApiMapCommand;
use Pushery\WireKit\Console\ExportBlocksCommand;
use Pushery\WireKit\Console\ExportJsonCommand;
use Pushery\WireKit\Console\GlassInstallCommand;
use Pushery\WireKit\Console\InstallCommand;
use Pushery\WireKit\Console\ListComponentsCommand;
use Pushery\WireKit\Console\ListFontsCommand;
use Pushery\WireKit\Console\ListIconsCommand;
use Pushery\WireKit\Console\MakeCommand;
use Pushery\WireKit\Console\PublishIconsCommand;
use Pushery\WireKit\Console\ShowComponentCommand;
use Pushery\WireKit\Console\ThemeCommand;
use Pushery\WireKit\Console\VerifyInstallationCommand;
use Pushery\WireKit\Fonts\FontRegistry;
use Pushery\WireKit\Icons\IconResolver;

class WireKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge package config with app config (user can override via vendor:publish)
        $this->mergeConfigFrom(__DIR__.'/../config/wirekit.php', 'wirekit');

        // Register WireKit as singleton so static state is scoped to the app container
        $this->app->singleton(WireKit::class, fn () => new WireKit);

        // IconResolver as singleton — one instance per request for caching
        $this->app->singleton(IconResolver::class);

        // ChartManager as singleton — caches the adapter instance per request
        $this->app->singleton(ChartManager::class);
    }

    public function boot(): void
    {
        // ── Publishable assets (FIRST — must register before anything that could fail) ──
        // Registered early so vendor:publish always works, even if later steps throw.
        if ($this->app->runningInConsole()) {
            // Register artisan commands
            $this->commands([
                ClassByAreaCommand::class,
                ComponentMakeCommand::class,
                CursorRulesCommand::class,
                DoctorA11yCommand::class,
                ExportApiMapCommand::class,
                ExportBlocksCommand::class,
                ExportJsonCommand::class,
                GlassInstallCommand::class,
                InstallCommand::class,
                ListComponentsCommand::class,
                ListFontsCommand::class,
                ListIconsCommand::class,
                MakeCommand::class,
                PublishIconsCommand::class,
                ShowComponentCommand::class,
                ThemeCommand::class,
                VerifyInstallationCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/wirekit.php' => config_path('wirekit.php'),
            ], 'wirekit-config');

            $this->publishes([
                __DIR__.'/../resources/views/components' => resource_path('views/vendor/wirekit/components'),
            ], 'wirekit-views');

            // Font files — published to public/vendor/wirekit/fonts/
            $this->publishes([
                __DIR__.'/../resources/fonts' => public_path('vendor/wirekit/fonts'),
            ], 'wirekit-fonts');

            // JavaScript bundles — published to public/vendor/wirekit/
            // Optional: improves performance by serving via web server instead of PHP route
            $this->publishes([
                __DIR__.'/../dist/wirekit.js' => public_path('vendor/wirekit/wirekit.js'),
                __DIR__.'/../dist/wirekit.core.js' => public_path('vendor/wirekit/wirekit.core.js'),
                __DIR__.'/../dist/wirekit.esm.js' => public_path('vendor/wirekit/wirekit.esm.js'),
                __DIR__.'/../dist/wirekit-apex.js' => public_path('vendor/wirekit/wirekit-apex.js'),
                __DIR__.'/../dist/wirekit-alpine.js' => public_path('vendor/wirekit/wirekit-alpine.js'),
            ], 'wirekit-scripts');

            // All assets (CSS + JS) — convenience tag for publishing everything at once
            $this->publishes([
                __DIR__.'/../dist/wirekit.css' => public_path('vendor/wirekit/wirekit.css'),
                __DIR__.'/../dist/wirekit.js' => public_path('vendor/wirekit/wirekit.js'),
                __DIR__.'/../dist/wirekit.core.js' => public_path('vendor/wirekit/wirekit.core.js'),
                __DIR__.'/../dist/wirekit.esm.js' => public_path('vendor/wirekit/wirekit.esm.js'),
                __DIR__.'/../dist/wirekit-apex.js' => public_path('vendor/wirekit/wirekit-apex.js'),
                __DIR__.'/../dist/wirekit-alpine.js' => public_path('vendor/wirekit/wirekit-alpine.js'),
            ], 'wirekit-assets');
        }

        // ── Views and Components ──
        // Load Blade views from resources/views with 'wirekit' namespace
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'wirekit');

        // Register anonymous Blade components with configurable prefix
        // Default: <x-wirekit::button>, <x-wirekit::input>, etc.
        // Uses anonymousComponentPath() on the compiler for file-based anonymous components
        $prefix = config('wirekit.prefix', 'wirekit');
        $this->app->afterResolving('blade.compiler', function (BladeCompiler $blade) use ($prefix) {
            $blade->anonymousComponentPath(__DIR__.'/../resources/views/components', $prefix);
        });

        // Register class-based Blade components with 'wirekit' prefix
        // Chart: <x-wirekit-chart> (class-based for DI support)
        $this->loadViewComponentsAs('wirekit', [
            Chart::class,
        ]);

        // ── Asset Routes ──
        // Register routes for serving assets without vendor:publish (like Livewire does).
        // Published assets take priority via the Blade directives below.
        $this->registerAssetRoutes();

        // ── Blade Directives ──
        // @wirekitStyles — outputs a <link> tag for wirekit.css.
        //
        // Two-tier serving strategy with automatic staleness detection:
        //
        //   1. If a published copy exists at public/vendor/wirekit/wirekit.css
        //      AND that copy is at least as new as the package's dist/wirekit.css,
        //      the web server serves it directly (fastest path).
        //
        //   2. Otherwise — either no published copy, OR a published copy that
        //      is older than the package's dist/ file (e.g. user ran
        //      `composer update pushery/wirekit` but forgot
        //      `vendor:publish --tag=wirekit-assets --force`) — we fall back to
        //      the route, which reads straight from the package's own dist/
        //      directory and is therefore guaranteed fresh after every
        //      `composer update`.
        //
        // The generated URL is cache-busted with `?v={filemtime}` so browsers
        // automatically pick up new content instead of serving a stale cached
        // copy forever. Since the URL changes whenever the file changes, the
        // asset route now serves with `Cache-Control: public, max-age=31536000,
        // immutable` (standard fingerprinted-asset caching, see
        // `registerAssetRoutes()` below).
        Blade::directive('wirekitStyles', function () {
            return '<?php
                $__wk_published = public_path(\'vendor/wirekit/wirekit.css\');
                $__wk_dist = \Pushery\WireKit\WireKitServiceProvider::distPath(\'wirekit.css\');
                $__wk_useRoute = ! file_exists($__wk_published)
                    || ($__wk_dist && filemtime($__wk_dist) > filemtime($__wk_published));
                if ($__wk_useRoute) {
                    $__wk_v = $__wk_dist ? filemtime($__wk_dist) : time();
                    echo \'<link rel="stylesheet" href="\' . url(\'/wirekit/wirekit.css\') . \'?v=\' . $__wk_v . \'">\' . "\n";
                } else {
                    $__wk_v = filemtime($__wk_published);
                    echo \'<link rel="stylesheet" href="\' . asset(\'vendor/wirekit/wirekit.css\') . \'?v=\' . $__wk_v . \'">\' . "\n";
                }
            ?>';
        });

        // @wirekitScripts — outputs a <script> tag for the configured JS bundle.
        // Same two-tier staleness-detection + cache-busting strategy as
        // @wirekitStyles above. See that directive for the full rationale.
        Blade::directive('wirekitScripts', function () {
            return '<?php
                $__wk_bundle = config("wirekit.scripts.bundle", "full");
                $__wk_file = $__wk_bundle === "core" ? "wirekit.core.js" : "wirekit.js";
                $__wk_published = public_path("vendor/wirekit/" . $__wk_file);
                $__wk_dist = \Pushery\WireKit\WireKitServiceProvider::distPath($__wk_file);
                $__wk_useRoute = ! file_exists($__wk_published)
                    || ($__wk_dist && filemtime($__wk_dist) > filemtime($__wk_published));
                if ($__wk_useRoute) {
                    $__wk_v = $__wk_dist ? filemtime($__wk_dist) : time();
                    echo \'<script src="\' . url("/wirekit/" . $__wk_file) . \'?v=\' . $__wk_v . \'" defer></script>\' . "\n";
                } else {
                    $__wk_v = filemtime($__wk_published);
                    echo \'<script src="\' . asset("vendor/wirekit/" . $__wk_file) . \'?v=\' . $__wk_v . \'" defer></script>\' . "\n";
                }

                // Force Livewire to inject its asset stack on this page even
                // when no Livewire component renders. WireKit components use
                // Alpine.js directives (x-data / x-bind / x-on) and Livewire
                // v3+ bundles Alpine — without forced injection, a pure-Blade
                // page (marketing / showcase / static) ships without Alpine
                // and every WireKit interactive component throws
                // "wirekitTableSort is not defined" in the console.
                //
                // Livewire only auto-injects when at least one Livewire
                // component renders (per SupportAutoInjectedAssets::shouldInjectLivewireAssets).
                // Calling forceAssetInjection() flips that flag so the
                // developer no longer needs @livewireScripts in the layout —
                // @wirekitStyles + @wirekitScripts is enough, and Alpine
                // arrives automatically via Livewire bundle.
                //
                // Guarded by class_exists() + method_exists() so installs
                // without Livewire OR with older Livewire versions silently
                // skip rather than crashing.
                if (class_exists(\Livewire\Livewire::class) && method_exists(\Livewire\Livewire::class, "forceAssetInjection")) {
                    try {
                        \Livewire\Livewire::forceAssetInjection();
                    } catch (\Throwable $__wk_e) {
                        // Swallow — defensive against future API changes.
                    }
                }
            ?>';
        });

        // Alpine x-transition directive — outputs shared transition attributes
        // Duration and easing come from CSS tokens (--transition-wk-duration, --transition-wk-easing)
        Blade::directive('wirekitTransition', fn () => '<?php echo "x-transition:enter=\"transition\" "'
            .'" x-transition:enter-start=\"opacity-0 scale-95\" "'
            .'" x-transition:enter-end=\"opacity-100 scale-100\" "'
            .'" x-transition:leave=\"transition\" "'
            .'" x-transition:leave-start=\"opacity-100 scale-100\" "'
            .'" x-transition:leave-end=\"opacity-0 scale-95\""; ?>');

        // ── Config Validation (local only) ──
        // Validate config keys in local environment only
        // In production, invalid keys silently fall back to defaults
        if (app()->environment('local')) {
            // Validate font presets
            $fontConfig = config('wirekit.fonts', []);

            foreach (['sans', 'serif', 'mono'] as $category) {
                $presetKey = $fontConfig[$category] ?? null;

                if ($presetKey === null) {
                    continue;
                }

                $preset = FontRegistry::get($presetKey);

                if ($preset === null) {
                    throw new \InvalidArgumentException(
                        "WireKit: Unknown font preset '{$presetKey}' for category '{$category}'. "
                        .'Available: '.implode(', ', array_keys(FontRegistry::category($category)))
                    );
                }
            }

            // Validate icon preset (checks preset exists without resolving aliases)
            app(IconResolver::class)->validatePreset();
        }
    }

    /**
     * Resolve the absolute path of an asset file inside the package's dist/
     * directory at runtime.
     *
     * Called from the `@wirekitStyles` / `@wirekitScripts` Blade directives to
     * (a) read the current `filemtime()` for cache-busting query strings, and
     * (b) compare against the published copy to detect staleness and fall back
     * to the route-based serving path when necessary.
     *
     * Uses `__DIR__` at call time so the path resolves relative to the real
     * location of this file, regardless of where the caller sits — crucial
     * because the Blade directive's PHP code is compiled into a cached view
     * file under `storage/framework/views/` where `__DIR__` would otherwise
     * point to the wrong directory.
     *
     * @param  string  $file  Filename inside dist/ (e.g. "wirekit.css")
     * @return string|null Absolute path if the file exists, null otherwise
     */
    public static function distPath(string $file): ?string
    {
        $path = __DIR__.'/../dist/'.$file;

        return file_exists($path) ? $path : null;
    }

    /**
     * Register routes that serve WireKit assets directly from the package.
     *
     * This allows @wirekitStyles and @wirekitScripts to work immediately
     * after `composer require` — no vendor:publish step needed.
     * Users can optionally publish assets for better performance (web server serving).
     */
    protected function registerAssetRoutes(): void
    {
        // Map route paths to dist/ files with their MIME types
        $assets = [
            'wirekit/wirekit.css' => ['file' => 'wirekit.css', 'type' => 'text/css'],
            'wirekit/wirekit.js' => ['file' => 'wirekit.js', 'type' => 'application/javascript'],
            'wirekit/wirekit.core.js' => ['file' => 'wirekit.core.js', 'type' => 'application/javascript'],
            'wirekit/wirekit.esm.js' => ['file' => 'wirekit.esm.js', 'type' => 'application/javascript'],
            'wirekit/wirekit-apex.js' => ['file' => 'wirekit-apex.js', 'type' => 'application/javascript'],
            'wirekit/wirekit-alpine.js' => ['file' => 'wirekit-alpine.js', 'type' => 'application/javascript'],
        ];

        Route::group(['middleware' => 'web'], function () use ($assets): void {
            foreach ($assets as $uri => $meta) {
                Route::get($uri, function () use ($meta) {
                    $path = realpath(__DIR__.'/../dist/'.$meta['file']);
                    $distDir = realpath(__DIR__.'/../dist');

                    // Guard against missing dist files and path traversal
                    if ($path === false || $distDir === false || ! str_starts_with($path, $distDir)) {
                        abort(404);
                    }

                    // 1-year immutable cache — safe because the @wirekitStyles
                    // and @wirekitScripts directives append a `?v={filemtime}`
                    // query string to every URL they generate, so any content
                    // change produces a new URL and the browser fetches fresh
                    // content automatically. This matches standard practice
                    // for fingerprinted assets (Vite, Mix, webpack, etc.).
                    return response(file_get_contents($path), 200, [
                        'Content-Type' => $meta['type'],
                        'Cache-Control' => 'public, max-age=31536000, immutable',
                    ]);
                });
            }
        });
    }
}
