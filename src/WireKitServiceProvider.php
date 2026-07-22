<?php

declare(strict_types=1);

namespace Pushery\WireKit;

use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use Pushery\WireKit\Charts\ChartManager;
use Pushery\WireKit\Components\Chart;
use Pushery\WireKit\Console\BoostSkillsCommand;
use Pushery\WireKit\Console\ClassByAreaCommand;
use Pushery\WireKit\Console\ComponentMakeCommand;
use Pushery\WireKit\Console\CursorRulesCommand;
use Pushery\WireKit\Console\DoctorA11yCommand;
use Pushery\WireKit\Console\EditorPresetCommand;
use Pushery\WireKit\Console\ExportApiMapCommand;
use Pushery\WireKit\Console\ExportBlocksCommand;
use Pushery\WireKit\Console\ExportJsonCommand;
use Pushery\WireKit\Console\GlassInstallCommand;
use Pushery\WireKit\Console\InstallCommand;
use Pushery\WireKit\Console\ListComponentsCommand;
use Pushery\WireKit\Console\ListFontsCommand;
use Pushery\WireKit\Console\ListIconsCommand;
use Pushery\WireKit\Console\MakeCommand;
use Pushery\WireKit\Console\McpServeCommand;
use Pushery\WireKit\Console\PublishFontsCommand;
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
        // Merge package config with app config (a developer overrides via
        // vendor:publish). RECURSIVELY, not with the framework's own
        // mergeConfigFrom, which is a flat array_merge:
        //
        //     $config->set($key, array_merge(require $path, $config->get($key, [])));
        //
        // A flat merge is correct only for a flat config. Ours nests — every
        // component's defaults live under `components.<name>` — so a published
        // `components` array REPLACES the package's entire section rather than
        // adding to it. Measured: a config published when it carried one
        // component override reduces 94 component sections to 1, and every key
        // added since becomes unreachable. Nothing fails; the components simply
        // fall back to their in-Blade defaults, and `config('wirekit.components.
        // theme-controller.variant')` returns null forever.
        //
        // The published file is a snapshot by design; it must not also be a
        // ceiling.
        $this->mergeConfigRecursivelyFrom(__DIR__.'/../config/wirekit.php', 'wirekit');

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
                BoostSkillsCommand::class,
                ClassByAreaCommand::class,
                ComponentMakeCommand::class,
                CursorRulesCommand::class,
                DoctorA11yCommand::class,
                EditorPresetCommand::class,
                ExportApiMapCommand::class,
                ExportBlocksCommand::class,
                ExportJsonCommand::class,
                GlassInstallCommand::class,
                InstallCommand::class,
                ListComponentsCommand::class,
                ListFontsCommand::class,
                ListIconsCommand::class,
                MakeCommand::class,
                McpServeCommand::class,
                PublishFontsCommand::class,
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

            // Translation reference — the JSON string-key master list.
            //
            // WireKit's components run every user- and screen-reader-visible
            // string through `__()` (JSON string keys — the English text IS the
            // key). `lang/en.json` is the complete, generated reference of every
            // such key. A localizing app publishes it and renames the copy to
            // its locale (`de.json`, `fr.json`, …), then translates the values —
            // Laravel's own JSON loader picks the app copy up automatically
            // because the keys match. Published into the app's lang directory so
            // it sits next to the developer's own translations.
            $this->publishes([
                __DIR__.'/../lang/en.json' => lang_path('vendor/wirekit/en.json'),
            ], 'wirekit-lang');

            // Font files — published to public/vendor/wirekit/fonts/
            //
            // This tag copies the WHOLE tree: 5.8 MB across sans, serif and mono.
            // An app that activates two families uses roughly 430 KB of that, so
            // the rest is dead weight in public/ — and it is re-copied on every
            // composer install when the publish is wired into post-autoload-dump
            // to survive deploys, which is the recommended setup.
            $this->publishes([
                __DIR__.'/../resources/fonts' => public_path('vendor/wirekit/fonts'),
            ], 'wirekit-fonts');

            // Per-preset publish tags, so an app can ship only what it activates
            // (WIRE-108). Each registered font gets `wirekit-font-<key>`:
            //
            //     php artisan vendor:publish --tag=wirekit-font-ibm-plex-sans
            //     php artisan vendor:publish --tag=wirekit-font-ibm-plex-mono
            //
            // Derived from FontRegistry rather than hand-listed, so a new preset
            // gets its tag automatically and the two can never drift apart. The
            // CSS path (e.g. "sans/inter/inter.css") locates the preset's own
            // directory, which holds that family's CSS and its woff2 files.
            foreach (FontRegistry::all() as $preset) {
                $dir = dirname($preset->cssFile);

                $this->publishes([
                    __DIR__.'/../resources/fonts/'.$dir => public_path('vendor/wirekit/fonts/'.$dir),
                ], 'wirekit-font-'.$preset->key);
            }

            // JavaScript bundles — published to public/vendor/wirekit/
            // Optional: improves performance by serving via web server instead of PHP route
            $this->publishes([
                __DIR__.'/../dist/wirekit.js' => public_path('vendor/wirekit/wirekit.js'),
                __DIR__.'/../dist/wirekit.core.js' => public_path('vendor/wirekit/wirekit.core.js'),
                __DIR__.'/../dist/wirekit.esm.js' => public_path('vendor/wirekit/wirekit.esm.js'),
                __DIR__.'/../dist/wirekit-apex.js' => public_path('vendor/wirekit/wirekit-apex.js'),
                __DIR__.'/../dist/wirekit-tiptap.js' => public_path('vendor/wirekit/wirekit-tiptap.js'),
                __DIR__.'/../dist/wirekit-alpine.js' => public_path('vendor/wirekit/wirekit-alpine.js'),
            ], 'wirekit-scripts');

            // All assets (CSS + JS) — convenience tag for publishing everything at once
            $this->publishes([
                __DIR__.'/../dist/wirekit.css' => public_path('vendor/wirekit/wirekit.css'),
                __DIR__.'/../dist/wirekit.js' => public_path('vendor/wirekit/wirekit.js'),
                __DIR__.'/../dist/wirekit.core.js' => public_path('vendor/wirekit/wirekit.core.js'),
                __DIR__.'/../dist/wirekit.esm.js' => public_path('vendor/wirekit/wirekit.esm.js'),
                __DIR__.'/../dist/wirekit-apex.js' => public_path('vendor/wirekit/wirekit-apex.js'),
                __DIR__.'/../dist/wirekit-tiptap.js' => public_path('vendor/wirekit/wirekit-tiptap.js'),
                __DIR__.'/../dist/wirekit-alpine.js' => public_path('vendor/wirekit/wirekit-alpine.js'),
            ], 'wirekit-assets');
        }

        // ── Translations ──
        // Register the package's JSON translations so any locale file WireKit
        // ships (today: the `en.json` source reference) merges into the global
        // JSON translation set. Runs OUTSIDE the console guard — translation
        // resolution happens at request time, not only when publishing. The
        // English text is the key, so an untranslated string falls back to
        // itself; an app that adds `lang/de.json` overrides per key.
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');

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

        // @wirekitThemeScript — the no-FOUC head script.
        //
        // Applies the stored theme BEFORE the first paint. This has to be an
        // inline, synchronous script in <head>: any deferred or external script
        // runs after the browser has already painted the light theme, and the
        // reader sees a white flash before the page turns dark. That flash is
        // the entire reason this directive exists, and it is why the script
        // cannot be folded into the main bundle.
        //
        // The reader half depends on the configured storage driver:
        //   'local'  — reads localStorage (client-only; this script IS the only
        //              thing that can apply the theme before paint).
        //   'cookie' — reads document.cookie. With this driver the server can
        //              already have rendered <html class="dark"> from the request
        //              cookie, so this script is a safety net (chiefly for the
        //              'system' case and for a first render the server did not
        //              resolve). It scans the cookie pair list by exact name — the
        //              same reader the Alpine control uses — so both agree.
        //
        // Takes an optional CSP nonce: @wirekitThemeScript($nonce). Apps without
        // a CSP pass nothing.
        Blade::directive('wirekitThemeScript', function ($expression) {
            $expression = trim($expression);
            $nonceExpr = $expression === '' ? "''" : $expression;

            return '<?php
                $__wk_nonce = '.$nonceExpr.';
                $__wk_key = config("wirekit.theme.storage_key", "wirekit-theme");
                $__wk_storage = config("wirekit.theme.storage", "local") === "cookie" ? "cookie" : "local";
                $__wk_nonceAttr = $__wk_nonce ? \' nonce="\' . e($__wk_nonce) . \'"\' : "";
                if ($__wk_storage === "cookie") {
                    // Scan document.cookie by exact name (no regex, so a key with
                    // regex-special characters cannot break the match). Mirrors the
                    // Alpine control\'s _readCookie().
                    $__wk_reader = \'var s=null,wc=(document.cookie||"").split("; ");\'
                        . \'for(var i=0;i<wc.length;i++){var we=wc[i].indexOf("="),wn=we<0?wc[i]:wc[i].slice(0,we);\'
                        . \'if(wn===\' . json_encode($__wk_key) . \'){s=decodeURIComponent(wc[i].slice(we+1));break;}}\';
                } else {
                    $__wk_reader = \'var s=localStorage.getItem(\' . json_encode($__wk_key) . \');\';
                }
                echo \'<script\' . $__wk_nonceAttr . \'>\'
                    . \'(function(){try{\' . $__wk_reader
                    // No stored choice means follow the OS — a first visit should
                    // look like the rest of the reader\'s machine, not like our
                    // default. An explicit choice always wins over the OS.
                    . \'var d=s==="dark"||(s!=="light"&&window.matchMedia("(prefers-color-scheme: dark)").matches);\'
                    . \'document.documentElement.classList.toggle("dark",d);\'
                    // localStorage throws in private mode and when storage is
                    // disabled entirely; the cookie reader cannot throw but is
                    // wrapped identically. Swallowing it leaves the OS preference
                    // in charge, which is the right fallback — never a broken page.
                    . \'}catch(e){}})();\'
                    . \'</scr\' . \'ipt>\' . "\n";
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
                // Calling forceAssetInjection() flips that flag so a pure-Blade
                // page (no Livewire component) still ships Alpine + the WireKit
                // plugins. The documented setup still emits BOTH @wirekitScripts
                // AND @livewireScripts in the canonical order; Livewire dedupes
                // its own assets, so this force-injection is a safety net for
                // Alpine-only pages, not a replacement for the layout directives.
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

        // ── Config Validation ──
        // Two distinct font-config problems, two distinct severities:
        //  - UNKNOWN preset key (a typo): FATAL in local so the developer sees it
        //    immediately; in production the value silently falls back to defaults
        //    (never fatally break a deployed page over a config typo).
        //  - KNOWN preset that is not published: WARN in EVERY environment (WIRE-108).
        //    The page still renders, but the developer's chosen font silently fell back
        //    to system fonts. This used to be undetectable in production; a throttled
        //    log line (once per preset per process) now surfaces it for ops, and the
        //    <x-wirekit::fonts> component renders an inert HTML comment in every env.
        $isLocal = app()->environment('local');
        $fontConfig = config('wirekit.fonts', []);

        foreach (['sans', 'serif', 'mono'] as $category) {
            $presetKey = $fontConfig[$category] ?? null;

            if ($presetKey === null) {
                continue;
            }

            $preset = FontRegistry::get($presetKey);

            if ($preset === null) {
                if ($isLocal) {
                    throw new \InvalidArgumentException(
                        "WireKit: Unknown font preset '{$presetKey}' for category '{$category}'. "
                        .'Available: '.implode(', ', array_keys(FontRegistry::category($category)))
                    );
                }

                continue; // production: unknown key falls back to defaults (unchanged)
            }

            // Known preset but not published → warn in all environments (WIRE-108).
            if (! file_exists(public_path($preset->publishedCssPath()))) {
                static::warnUnpublishedFont($category, $presetKey);
            }
        }

        if ($isLocal) {
            // Validate icon preset (checks preset exists without resolving aliases)
            app(IconResolver::class)->validatePreset();
        }
    }

    /**
     * Guards the unpublished-font warning to once per preset key per process, so a
     * persistent misconfiguration logs a single actionable line instead of flooding
     * the log with one entry per request/render. Reset in tests via a direct assign.
     *
     * @var array<string, bool>
     */
    public static array $unpublishedFontWarned = [];

    /**
     * Log a single warning that a configured, known font preset is not published and
     * text is therefore falling back to system fonts (WIRE-108). Throttled per process.
     */
    protected static function warnUnpublishedFont(string $category, string $presetKey): void
    {
        if (isset(static::$unpublishedFontWarned[$presetKey])) {
            return;
        }

        static::$unpublishedFontWarned[$presetKey] = true;

        Log::warning(
            "WireKit: font preset '{$presetKey}' ({$category}) is configured but its CSS is not "
            .'published — text is falling back to system fonts. Run '
            .'`php artisan vendor:publish --tag=wirekit-fonts` to ship the bundled font.'
        );
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
            'wirekit/wirekit-tiptap.js' => ['file' => 'wirekit-tiptap.js', 'type' => 'application/javascript'],
            'wirekit/wirekit-alpine.js' => ['file' => 'wirekit-alpine.js', 'type' => 'application/javascript'],
        ];

        // Bundled fonts, served straight from the package when they were never
        // published (WIRE-108). Without this the fonts component emitted no
        // <link> at all for a configured-but-unpublished family, and the page
        // fell back to system fonts — a difference nobody notices in review and
        // everybody notices in production, because the app looked right locally
        // where the publish had been run once by hand.
        //
        // The route serves the family's whole directory: the CSS and the woff2
        // files it @font-face-references, which are siblings of it.
        Route::group(['middleware' => 'web'], function (): void {
            Route::get('wirekit/fonts/{path}', function (string $path) {
                $root = realpath(__DIR__.'/../resources/fonts');
                $file = realpath(__DIR__.'/../resources/fonts/'.$path);

                // Path traversal: a resolved path that escapes the font root is
                // refused rather than served.
                if ($root === false || $file === false || ! str_starts_with($file, $root) || ! is_file($file)) {
                    abort(404);
                }

                $type = match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
                    'css' => 'text/css; charset=utf-8',
                    'woff2' => 'font/woff2',
                    'woff' => 'font/woff',
                    'ttf' => 'font/ttf',
                    default => abort(404),
                };

                return response(file_get_contents($file), 200, [
                    'Content-Type' => $type,
                    // Same one-year immutable policy as the other assets. The URL
                    // carries a ?v={filemtime} from the component, so new content
                    // is a new URL.
                    'Cache-Control' => 'public, max-age=31536000, immutable',
                ]);
            })->where('path', '.*');
        });

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

    /**
     * Merge package config into the app's, recursing into nested arrays.
     *
     * The framework's mergeConfigFrom is a flat array_merge, which silently
     * freezes a nested section: a developer who published `config/wirekit.php`
     * once keeps exactly the keys it had that day, and every key added by a later
     * release is unreachable. For a config whose whole shape is
     * `components.<name>.<option>`, that is the difference between 94 usable
     * component sections and 1.
     *
     * The app always wins on a scalar — an override is an override. Recursion
     * only ADDS what the app never mentioned.
     */
    protected function mergeConfigRecursivelyFrom(string $path, string $key): void
    {
        if ($this->app instanceof CachesConfiguration
            && $this->app->configurationIsCached()) {
            return;
        }

        $config = $this->app->make('config');

        $config->set($key, $this->mergeConfigArrays(require $path, $config->get($key, [])));
    }

    /**
     * @param  array<string, mixed>  $package
     * @param  array<string, mixed>  $app
     * @return array<string, mixed>
     */
    private function mergeConfigArrays(array $package, array $app): array
    {
        foreach ($package as $key => $value) {
            if (! array_key_exists($key, $app)) {
                $app[$key] = $value;

                continue;
            }

            // Recurse only where BOTH sides are associative. A list (icon
            // presets, a locale array) is a value the developer chose wholesale:
            // merging into it would resurrect entries they deliberately removed.
            if (is_array($value) && is_array($app[$key]) && ! array_is_list($value) && ! array_is_list($app[$key])) {
                $app[$key] = $this->mergeConfigArrays($value, $app[$key]);
            }
        }

        return $app;
    }
}
