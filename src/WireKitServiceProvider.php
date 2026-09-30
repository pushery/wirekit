<?php

declare(strict_types=1);

namespace Pushery\WireKit;

use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\ComponentAttributeBag;
use Pushery\WireKit\Charts\ChartManager;
use Pushery\WireKit\Components\Chart;
use Pushery\WireKit\Components\FieldSet;
use Pushery\WireKit\Components\SidebarGroup;
use Pushery\WireKit\Console\BoostSkillsCommand;
use Pushery\WireKit\Console\ClassByAreaCommand;
use Pushery\WireKit\Console\ComponentMakeCommand;
use Pushery\WireKit\Console\CspAuditCommand;
use Pushery\WireKit\Console\CursorRulesCommand;
use Pushery\WireKit\Console\DoctorA11yCommand;
use Pushery\WireKit\Console\DoctorPropsCommand;
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
use Pushery\WireKit\Fonts\FontCss;
use Pushery\WireKit\Fonts\FontRegistry;
use Pushery\WireKit\Icons\IconResolver;
use Pushery\WireKit\Support\BaseLocaleJsonLoader;
use Pushery\WireKit\Support\DomId;
use Pushery\WireKit\Support\FaqCollector;
use Pushery\WireKit\Support\FlagPackage;
use Pushery\WireKit\Support\LogThrottle;
use Pushery\WireKit\Support\StrictnessGate;
use Pushery\WireKit\Support\TourStepCounter;
use Pushery\WireKit\Support\UnboundModel;
use Pushery\WireKit\Support\ValuelessDirectives;

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
        // adding to it: a config published with one component override would
        // reduce every component section to that one, and each key added since
        // would be unreachable. Nothing would fail; the components would simply
        // fall back to their in-Blade defaults, and `config('wirekit.components.
        // theme-controller.variant')` would return null forever.
        //
        // The published file is a snapshot by design; it must not also be a
        // ceiling.
        $this->mergeConfigRecursivelyFrom(__DIR__.'/../config/wirekit.php', 'wirekit');

        // Bound as a singleton for code that resolves it from the container. Its state is
        // static and not scoped to the container; `WireKit::flush()` is what clears it.
        $this->app->singleton(WireKit::class, fn () => new WireKit);

        // IconResolver as a singleton, so one alias cache serves every icon the container
        // renders. Octane clones the booted application for each request, and boot() resolves
        // this one in the local environment, so there it lives as long as the worker.
        $this->app->singleton(IconResolver::class);

        // ChartManager as a singleton, so the adapter it builds serves every chart the
        // container renders.
        $this->app->singleton(ChartManager::class);

        // ── Regional locales reach the shipped catalogs ──
        // Laravel's JSON translation channel matches the locale filename
        // exactly, and `fallback_locale` never reaches it — the fallback loop
        // in `Translator::get()` runs after the JSON lookup has already missed
        // and walks the PHP-group path only. So an app whose locale is `pt-PT`
        // looks for a `pt-PT.json` nobody ships and renders English inside a
        // Portuguese page, with a complete `pt.json` sitting unread in the
        // directory `loadJsonTranslationsFrom()` registers below. Every
        // regional variant of every language shipped lands the same way, and
        // nothing looks broken, because English is also what a genuinely
        // untranslated key renders as.
        //
        // The decorator closes it for THIS package's lang directory alone (the
        // reasoning for that narrowness is in the class docblock).
        //
        // It belongs in register(), never in boot(): the Translator receives
        // its loader by constructor injection, so once `translator` has been
        // resolved, replacing the `translation.loader` binding leaves the live
        // Translator holding the loader it already has — the container would
        // then report a decorator that nothing actually reads through. Hence
        // the guard rather than an unconditional extend: if something resolved
        // the translator before this provider ran, stay out of the way
        // completely. A no-op is honest; a decorator that is installed and
        // unreachable is not.
        if (! $this->app->resolved('translator')) {
            $this->app->extend(
                'translation.loader',
                // The bridge flag is read HERE and passed in, never inside `load()`. This runs
                // in register(), where a config cache is not a safe assumption yet, and a
                // loader that calls `config()` on every lookup would read it hundreds of times
                // per request to get the same answer.
                fn (Loader $loader): BaseLocaleJsonLoader => new BaseLocaleJsonLoader(
                    $loader,
                    __DIR__.'/../lang',
                    (bool) ($this->app['config']->get('wirekit.translations.legacy_key_bridge', true)),
                ),
            );
        }
    }

    public function boot(): void
    {
        // Reset the per-render registries after each request and before each queued job: DOM
        // ids start clean, a rejected prop value is reported again, tour steps count from zero,
        // and a question an orphaned faq-item left behind reaches no later FAQ. The registries
        // are static, and static state lasts as long as the PHP script that holds it: FPM starts
        // the script afresh for every HTTP request, so they start empty there anyway, while an
        // Octane worker and a queue worker run one script across many requests or jobs. A queue
        // worker never finishes an HTTP request, hence the second listener; WireKit::flush()
        // covers tests.
        $resetPerRender = static function (): void {
            DomId::reset();
            StrictnessGate::forgetLogged();
            TourStepCounter::reset();
            FaqCollector::reset();
        };
        $this->app['events']->listen(RequestHandled::class, $resetPerRender);
        $this->app['events']->listen(JobProcessing::class, $resetPerRender);

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
                DoctorPropsCommand::class,
                EditorPresetCommand::class,
                ExportApiMapCommand::class,
                ExportBlocksCommand::class,
                CspAuditCommand::class,
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
            // string through `__()` under the `wirekit::` key prefix, so a key
            // reads `wirekit::Close` and its English VALUE reads `Close`.
            // `lang/en.json` is the complete, generated reference of every such
            // key.
            //
            // The English wording is the VALUE, not the key, which is why an
            // app-side catalog written from memory resolves nothing: an app's
            // own `de.json` keyed by the English is a different key from the one
            // a component asks for. Copy the keys out of the reference rather
            // than retyping the English.
            //
            // WHAT THE PUBLISHED COPY IS: an inert REFERENCE, not a load path.
            // Laravel's JSON loader reads `lang/{locale}.json` plus any directory
            // registered through `addJsonPath()`, and `lang/vendor/wirekit/` is
            // neither — so a `de.json` placed at the destination below is never
            // read. To translate, COPY the reference to the app's lang root
            // (`cp lang/vendor/wirekit/en.json lang/de.json`) and translate the
            // values there; the keys match, so the app copy wins per key.
            //
            // Renaming the published copy in place does nothing, and silently: the
            // copy has to move to the lang root. docs/localization.md describes the
            // same path.
            //
            // Every shipped catalog, not just the reference. A translated one is
            // already ACTIVE without publishing — `loadJsonTranslationsFrom`
            // below sees it — so this tag exists for the developer who wants to
            // adjust a phrase to their own product's voice, and that is as true
            // of German as of English.
            //
            // Derived from the files rather than listed: a publish tag cannot notice a
            // catalog it was never told about, so a hand-written list would keep
            // publishing the languages it named while new ones shipped beside it. A
            // catalog is published by existing, and the next language needs no edit
            // here. The glob runs only under `runningInConsole()`, so it costs the
            // request path nothing.
            $catalogs = [];

            foreach (glob(__DIR__.'/../lang/*.json') ?: [] as $catalog) {
                $catalogs[$catalog] = lang_path('vendor/wirekit/'.basename($catalog));
            }

            $this->publishes($catalogs, 'wirekit-lang');

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

            // Flags, published with their manifest to public/vendor/wirekit/flags/, so the web
            // server serves them and the flag component can tell a current copy from a stale
            // one. The same shape as the fonts above: the artwork ships with the package and is
            // published on request, never loaded by a page that does not ask for a flag.
            //
            // Registered unconditionally since the artwork moved in-package. `root()` can still
            // be null when `wirekit.flags.path` is set to somewhere that holds no manifest, and
            // publishing from a path that does not exist would fail at `vendor:publish` rather
            // than here — so the guard stays, for a case that is now a mistake rather than an
            // absent dependency.
            $flagRoot = FlagPackage::root();

            if ($flagRoot !== null) {
                $this->publishes([
                    $flagRoot => public_path('vendor/wirekit/flags'),
                ], 'wirekit-flags');
            }

            // Per-preset publish tags, so an app can ship only what it activates
            // itself. Each registered font gets `wirekit-font-<key>`:
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
                __DIR__.'/../dist/wirekit-optimistic.js' => public_path('vendor/wirekit/wirekit-optimistic.js'),
                __DIR__.'/../dist/wirekit-theme.js' => public_path('vendor/wirekit/wirekit-theme.js'),
                __DIR__.'/../dist/wirekit-alpine.js' => public_path('vendor/wirekit/wirekit-alpine.js'),
                __DIR__.'/../dist/wirekit-alpine.csp.js' => public_path('vendor/wirekit/wirekit-alpine.csp.js'),
            ], 'wirekit-scripts');

            // All assets (CSS + JS) — convenience tag for publishing everything at once
            $this->publishes([
                __DIR__.'/../dist/wirekit.css' => public_path('vendor/wirekit/wirekit.css'),
                __DIR__.'/../dist/wirekit.js' => public_path('vendor/wirekit/wirekit.js'),
                __DIR__.'/../dist/wirekit.core.js' => public_path('vendor/wirekit/wirekit.core.js'),
                __DIR__.'/../dist/wirekit.esm.js' => public_path('vendor/wirekit/wirekit.esm.js'),
                __DIR__.'/../dist/wirekit-apex.js' => public_path('vendor/wirekit/wirekit-apex.js'),
                __DIR__.'/../dist/wirekit-tiptap.js' => public_path('vendor/wirekit/wirekit-tiptap.js'),
                __DIR__.'/../dist/wirekit-optimistic.js' => public_path('vendor/wirekit/wirekit-optimistic.js'),
                __DIR__.'/../dist/wirekit-theme.js' => public_path('vendor/wirekit/wirekit-theme.js'),
                __DIR__.'/../dist/wirekit-alpine.js' => public_path('vendor/wirekit/wirekit-alpine.js'),
                __DIR__.'/../dist/wirekit-alpine.csp.js' => public_path('vendor/wirekit/wirekit-alpine.csp.js'),
                __DIR__.'/../dist/wirekit.min.css' => public_path('vendor/wirekit/wirekit.min.css'),
            ], 'wirekit-assets');
        }

        // ── Translations ──
        // Register the package's JSON catalogs so every `lang/*.json` file
        // WireKit ships merges into the global JSON translation set. Runs
        // OUTSIDE the console guard — translation resolution happens at request
        // time, not only when publishing.
        //
        // A key that no catalog resolves does NOT fall back to readable English:
        // the keys carry the `wirekit::` prefix, so an unresolved one paints
        // `wirekit::Close` onto the page. That is what the English backstop in
        // `Support\BaseLocaleJsonLoader` is for — `en.json` is merged underneath
        // every locale, including the ones nothing ships a catalog for. An app
        // that adds its own `lang/de.json` still overrides per key.
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');

        // ── Views and Components ──
        // Load Blade views from resources/views with 'wirekit' namespace
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'wirekit');

        // Register anonymous Blade components with configurable prefix
        // Default: <x-wirekit::button>, <x-wirekit::input>, etc.
        // Uses anonymousComponentPath() on the compiler for file-based anonymous components
        $prefix = config('wirekit.prefix', 'wirekit');

        // `callAfterResolving`, not `$this->app->afterResolving`.
        //
        // The container's own hook fires only on a resolution that happens LATER. In an
        // application where anything reached Blade first — another provider rendering a
        // view, a cached route file, a view composer — the callback never ran, the path
        // was never registered, and every `<x-{prefix}::*>` tag threw "Unable to locate a
        // class or view for component". The documented `wirekit.prefix` was inert exactly
        // where an application is realistic, and green in a test where the provider boots
        // first.
        //
        // The framework's helper is that hook PLUS "call it now if the service is already
        // resolved" — the half that was missing.
        $this->callAfterResolving('blade.compiler', function (BladeCompiler $blade) use ($prefix) {
            $componentsPath = __DIR__.'/../resources/views/components';
            $blade->anonymousComponentPath($componentsPath, $prefix);

            // A directive written on a tag without a value reaches the view as `true`, which the
            // attribute bag renders as the directive's own name, and Alpine throws on a bare
            // `x-transition` rendered that way (Support\ValuelessDirectives). Every component
            // view gives such a directive an empty value before it renders. The anonymous views
            // live in the namespace Laravel derives from the prefix, read back here rather than
            // recomputed; the class components render theirs under `wirekit::`.
            $namespaces = ['wirekit::components.*'];

            foreach ($blade->getAnonymousComponentPaths() as $registered) {
                if ($registered['path'] === $componentsPath) {
                    $namespaces[] = $registered['prefixHash'].'::*';
                }
            }

            // The same composer removes a caller's `x-model` from a component whose own Alpine root
            // takes the caller's attributes and holds no value to bind (Support\UnboundModel).
            $this->app->make('view')->composer($namespaces, static function (View $view): void {
                $attributes = $view->getData()['attributes'] ?? null;

                if ($attributes instanceof ComponentAttributeBag) {
                    ValuelessDirectives::normalize($attributes);
                    UnboundModel::dropFromView($view->name(), $attributes);
                }
            });

            // Two files on that path have a class behind them, under the same tag. The markup and
            // the declared props stay in the view, so every reader of `@props` still finds them
            // there. A set hands the controls in its slot what they point at, and a slot renders
            // before the view that holds it; only a constructor runs early enough.
            $blade->component(FieldSet::class, $prefix.'::field.set');

            // The group does the opposite: it keeps its own `collapsible` from the rows in its
            // slot, which read the sidebar's through `@aware`.
            $blade->component(SidebarGroup::class, $prefix.'::sidebar.group');
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
        // @wirekitStyles — outputs a <link> tag for wirekit.min.css.
        //
        // The MINIFIED twin, not the readable dist/wirekit.css. That file is the source of
        // truth for every design token and stays published so a developer can read it, but
        // most of it is comments and it is RENDER-BLOCKING: linking it would cost every
        // uncached page view several times the gzipped size of the rules it delivers.
        //
        // Two-tier serving strategy with automatic staleness detection:
        //
        // The two steps below name no file on purpose. `styleTag()` takes the filename as an
        // argument and both twins are published side by side, so a staleness rule written
        // around one of them is wrong for the other and goes stale the moment the directive
        // is repointed — which is exactly what happened when this directive moved to the
        // minified twin and these lines kept describing the readable one, sending anyone
        // chasing a stale stylesheet to the file that is not being served. The heading above
        // may name the file because the directive body hardcodes it; the rule may not.
        //
        //   1. If a published copy of the file this directive links exists under
        //      public/vendor/wirekit/ AND carries the same bytes as the package's own copy
        //      in dist/ (`publishedIsStale()`: the sizes first, a hash when they agree), the
        //      web server serves it directly (fastest path).
        //
        //   2. Otherwise — either no published copy, OR one that differs from the package's
        //      (a `composer update pushery/wirekit` without the matching
        //      `vendor:publish --tag=wirekit-assets --force`) — we fall back to the route,
        //      which reads straight from the package's own dist/ directory and is therefore
        //      guaranteed fresh after every `composer update`.
        //
        // The generated URL is cache-busted with `?v={filemtime}` so browsers
        // automatically pick up new content instead of serving a stale cached
        // copy forever. Since the URL changes whenever the file changes, the
        // asset route now serves with `Cache-Control: public, max-age=31536000,
        // immutable` (standard fingerprinted-asset caching, see
        // `registerAssetRoutes()` below).
        // Takes an optional CSP nonce: @wirekitStyles($nonce). Apps without a CSP
        // pass nothing and the attribute is omitted entirely.
        //
        // Why a stylesheet link needs one at all: under a 'self'-based policy it
        // does not — same origin, allowed. Under the 'strict-dynamic' shape OWASP
        // recommends, the nonce is the ONLY thing that grants a resource, so a
        // <link> without one is blocked and the app has no lever to fix it from
        // the outside. The seam already existed on @wirekitThemeScript; it was
        // simply never carried to the other two directives.
        Blade::directive('wirekitStyles', function ($expression) {
            $expression = trim($expression);
            $nonceExpr = $expression === '' ? "''" : $expression;

            // The body calls the shared helper rather than inlining the published-vs-dist
            // decision a second time, so the staleness rule cannot diverge between the
            // stylesheet and the scripts.
            return '<?php
                $__wk_nonce = '.$nonceExpr.';
                $__wk_nonceAttr = $__wk_nonce ? \' nonce="\' . e($__wk_nonce) . \'"\' : "";
                echo \Pushery\WireKit\WireKitServiceProvider::styleTag(\'wirekit.min.css\', $__wk_nonceAttr);
            ?>';
        });

        // @wirekitThemeScript — the no-FOUC head script.
        //
        // Applies the stored theme BEFORE the first paint. It has to be a plain, classic script at the
        // top of <head>: the browser holds the page until such a script has run, so the theme is set
        // before anything paints. A deferred, async or module script runs after the first paint, the
        // reader sees a light page turn dark, and preventing that flash is the entire reason this
        // directive exists. It is also why the script cannot ride the main bundle.
        //
        // The script is dist/wirekit-theme.js, one file for both deliveries; see themeScriptTag().
        // It is written into the page by default, or loaded as that file when `wirekit.theme.script`
        // is 'external', for a policy that allows scripts from the application's own origin and no
        // inline script at all.
        //
        // Takes an optional CSP nonce: @wirekitThemeScript($nonce). Apps without a CSP pass nothing.
        Blade::directive('wirekitThemeScript', function ($expression) {
            $expression = trim($expression);
            $nonceExpr = $expression === '' ? "''" : $expression;

            return '<?php
                $__wk_nonce = '.$nonceExpr.';
                $__wk_nonceAttr = $__wk_nonce ? \' nonce="\' . e($__wk_nonce) . \'"\' : "";
                echo \Pushery\WireKit\WireKitServiceProvider::themeScriptTag($__wk_nonceAttr);
            ?>';
        });

        // @wirekitScripts — outputs a <script> tag for the configured JS bundle.
        // Same two-tier staleness-detection + cache-busting strategy as
        // @wirekitStyles above. See that directive for the full rationale.
        // Takes an optional CSP nonce: @wirekitScripts($nonce). Same reasoning as
        // @wirekitStyles above — under 'strict-dynamic' the nonce is what grants
        // the script, and without this parameter a developer had no way to supply
        // one for the bundle tag.
        Blade::directive('wirekitScripts', function ($expression) {
            $expression = trim($expression);
            $nonceExpr = $expression === '' ? "''" : $expression;

            return '<?php
                $__wk_nonce = '.$nonceExpr.';
                $__wk_nonceAttr = $__wk_nonce ? \' nonce="\' . e($__wk_nonce) . \'"\' : "";
                $__wk_bundle = config("wirekit.scripts.bundle", "full");
                // An unknown value falls back to `full` rather than to nothing:
                // a typo here would otherwise ship a page with no WireKit
                // JavaScript, which fails silently — components simply stop
                // being interactive with no error anywhere.
                $__wk_file = match ($__wk_bundle) {
                    "core" => "wirekit.core.js",
                    // Self-contained AND Alpine-bundled, unlike its two
                    // siblings: built against Alpine\'s CSP distribution so no
                    // \'unsafe-eval\' is needed. An app on this option must NOT
                    // also load its own Alpine.
                    "csp" => "wirekit-alpine.csp.js",
                    default => "wirekit.js",
                };
                // The overlay landmark\'s accessible name rides this tag. See
                // WireKitServiceProvider::overlayLabelAttribute() for why it travels here
                // rather than in an inline script or in markup of its own.
                echo \Pushery\WireKit\WireKitServiceProvider::scriptTag(
                    $__wk_file,
                    $__wk_nonceAttr,
                    \Pushery\WireKit\WireKitServiceProvider::overlayLabelAttribute(),
                );

                // The ApexCharts adapter, opt-in via config.
                //
                // It is a SEPARATE bundle so an app that draws no charts pays no bytes, and
                // that is worth keeping — but the failure when it is missing was brutal and
                // silent: `<x-wirekit::chart library="apexcharts">` with window.ApexCharts
                // correctly loaded throws "wirekitApexChart is not defined" in the console,
                // the page renders normally, and only the chart is absent. No build error, no
                // server-side signal. Every developer who hit it had to discover that a second
                // file existed and hand-write a script tag against a route whose path is not
                // documented anywhere they were looking.
                //
                // One config line now emits it in the right place and order instead.
                if (config("wirekit.scripts.apex", false)) {
                    echo \Pushery\WireKit\WireKitServiceProvider::scriptTag("wirekit-apex.js", $__wk_nonceAttr);
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
                // Asked of the MANAGER, the way Livewire\'s own `@persist` does
                // (`app("livewire")->forceAssetInjection()`). `Livewire\Livewire` is a
                // facade that reaches the method through `__callStatic`, which
                // method_exists() does not see, so a check against the facade is
                // false on every install. Guarded so an install without Livewire,
                // or with a version that lacks the method, skips rather than crashes.
                //
                // Never for the `csp` bundle. It carries its own Alpine, built for a
                // policy without unsafe-eval, and the assets Livewire injects start a
                // second one on the same page, which evaluates every expression with
                // the function constructor that such a policy refuses.
                if ($__wk_bundle !== "csp" && class_exists(\Livewire\LivewireManager::class) && method_exists(\Livewire\LivewireManager::class, "forceAssetInjection") && app()->bound("livewire")) {
                    try {
                        app("livewire")->forceAssetInjection();
                    } catch (\Throwable $__wk_e) {
                        // Swallow — defensive against future API changes.
                    }
                }
            ?>';
        });

        // Alpine x-transition directive — outputs shared transition attributes. Duration and
        // easing read the CSS tokens (--transition-wk-duration, --transition-wk-easing) through
        // the same arbitrary-value utilities the component templates use, so changing a token
        // changes this transition too; a bare `transition` would read Tailwind's own defaults.
        // The attributes are plain text, which Blade writes into the compiled view unchanged:
        // nothing in them is evaluated at render time.
        Blade::directive('wirekitTransition', fn (): string => implode(' ', [
            'x-transition:enter="transition duration-[var(--transition-wk-duration)] ease-[var(--transition-wk-easing)]"',
            'x-transition:enter-start="opacity-0 scale-95"',
            'x-transition:enter-end="opacity-100 scale-100"',
            'x-transition:leave="transition duration-[var(--transition-wk-duration)] ease-[var(--transition-wk-easing)]"',
            'x-transition:leave-start="opacity-100 scale-100"',
            'x-transition:leave-end="opacity-0 scale-95"',
        ]));

        // ── Config Validation ──
        // Two distinct font-config problems, two distinct severities:
        //  - UNKNOWN preset key (a typo): FATAL in local so the developer sees it
        //    immediately; in production the value silently falls back to defaults
        //    (never fatally break a deployed page over a config typo).
        //  - KNOWN preset that is not published: WARN in EVERY environment.
        //    The page still renders, but the developer's chosen font silently fell back
        //    to system fonts. In production a log line, at most one per preset and hour,
        //    is how ops learns of it, and the <x-wirekit::fonts> component renders an
        //    inert HTML comment in every env.
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

            // Known preset but not published → warn in all environments.
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
     * The preset keys already warned about in this request, so the cache is asked once per
     * key. Reset in tests via a direct assign, which is also what the next request under FPM
     * starts with.
     *
     * @var array<string, bool>
     */
    public static array $unpublishedFontWarned = [];

    /**
     * Log a warning that a configured, known font preset is not published and text is
     * therefore falling back to system fonts. {@see LogThrottle} lets it through once per
     * preset and hour across requests, so a persistent misconfiguration writes one
     * actionable line rather than one per request.
     */
    protected static function warnUnpublishedFont(string $category, string $presetKey): void
    {
        if (isset(static::$unpublishedFontWarned[$presetKey])) {
            return;
        }

        static::$unpublishedFontWarned[$presetKey] = true;

        if (! LogThrottle::firstAcrossRequests('unpublished-font:'.$presetKey)) {
            return;
        }

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
     * The URL of a dist file, with a version query: the published copy when it is current, the
     * package's asset route when that copy is missing or stale.
     *
     * Extracted from scriptTag() when the theme script became a second tag that needs the same
     * decision. The staleness check exists so a developer who published the assets once and then
     * upgraded the package does not silently keep serving the old file.
     */
    protected static function assetSource(string $file): string
    {
        $published = public_path('vendor/wirekit/'.$file);
        $dist = self::distPath($file);

        if (self::publishedIsStale($published, $dist)) {
            $version = $dist ? filemtime($dist) : time();

            return url('/wirekit/'.$file).'?v='.$version;
        }

        return asset('vendor/wirekit/'.$file).'?v='.filemtime($published);
    }

    /**
     * The `@wirekitThemeScript` tag: dist/wirekit-theme.js, written into the page or loaded as a file.
     *
     * One script serves both deliveries because it reads its configuration from its own tag, from
     * `data-wk-theme-storage` and `data-wk-theme-key`, instead of having values written into its
     * code. The inline form therefore puts nothing but the file's own code inside the script block,
     * and the file form loads the same code.
     *
     * The file form is a classic tag with no `defer` and no `async`, unlike the bundle tags: it has to
     * hold the page until the theme is set, which is exactly what a deferred script does not do.
     *
     * @param  string  $nonceAttr  pre-escaped ` nonce="…"` or an empty string
     */
    public static function themeScriptTag(string $nonceAttr = ''): string
    {
        $storage = config('wirekit.theme.storage', 'local') === 'cookie' ? 'cookie' : 'local';
        $attributes = $nonceAttr
            .' data-wk-theme-storage="'.$storage.'"'
            .' data-wk-theme-key="'.e((string) config('wirekit.theme.storage_key', 'wirekit-theme')).'"';

        if (config('wirekit.theme.script', 'inline') === 'external') {
            return '<script'.$attributes.' src="'.self::assetSource('wirekit-theme.js').'"></script>'."\n";
        }

        return '<script'.$attributes.'>'.self::themeScriptSource().'</script>'."\n";
    }

    /**
     * The theme script's code without its license banner, read once per process.
     *
     * The banner is the only comment in the built file, and a page does not need a license notice
     * written into it.
     */
    protected static function themeScriptSource(): string
    {
        static $source = null;

        if ($source === null) {
            $path = self::distPath('wirekit-theme.js');

            if ($path === null) {
                // Said out loud: an empty script block renders cleanly and sets no theme at all.
                logger()->warning('WireKit: dist/wirekit-theme.js is missing, so @wirekitThemeScript renders an empty script. Reinstall the package.');
            }

            $code = $path === null ? '' : (string) file_get_contents($path);
            $source = trim((string) preg_replace('#\A/\*!.*?\*/#s', '', $code));
        }

        return $source;
    }

    /**
     * Build one `<script>` tag for a bundle, published copy or package route.
     *
     * Extracted from the `@wirekitScripts` directive when the ApexCharts adapter became a
     * second, optional tag. Duplicating the published-vs-route decision and the cache-busting
     * timestamp would have meant two copies of a rule that is easy to get subtly wrong — the
     * staleness check exists so a developer who published assets once and then upgraded the
     * package does not silently keep serving the old file.
     *
     * @param  string  $file  bundle filename, e.g. `wirekit.js`
     * @param  string  $nonceAttr  pre-escaped ` nonce="…"` or an empty string
     * @param  string  $extraAttrs  pre-escaped extra attributes, each with its own leading space
     */
    public static function scriptTag(string $file, string $nonceAttr = '', string $extraAttrs = ''): string
    {
        $src = self::assetSource($file);

        // `data-navigate-once` — the bundle registers Alpine components and document
        // listeners, and that is a once-per-DOCUMENT job. Livewire's navigate re-executes a
        // head script on every client-side hop unless the attribute is there, as its own
        // bundle's tag has it. Without it the bundle runs once more per hop, and the
        // listener set grows for as long as somebody navigates without a full reload,
        // which in a console shell is the normal way to use the app.
        //
        // Counting <script> tags does not see it — the old tag is replaced rather than added,
        // so the DOM keeps showing exactly one; `performance.getEntriesByType('resource')`
        // across two real hops does.
        //
        // Unconditional rather than gated on Livewire being installed. It is an inert data
        // attribute to anything that does not read it, and a detection branch here would be
        // a second thing to keep right for no gain.
        return '<script'.$nonceAttr.$extraAttrs.' data-navigate-once="true" src="'.$src.'" defer></script>'."\n";
    }

    /**
     * The translated accessible name for the overlay landmark, as an HTML attribute.
     *
     * Every teleported panel lives inside one landmark, and a landmark exists to be
     * announced — so its name is read by exactly the people who cannot see that the
     * region is empty of meaning otherwise. It was a literal in the bundle, which meant
     * an application shipping eight languages announced one English word in eight
     * otherwise translated region lists.
     *
     * The name rides the bundle's own `<script>` tag rather than an inline script or a
     * `<div>` of its own, and both alternatives were rejected for reasons worth keeping:
     * an inline script is a second thing a strict Content-Security-Policy must nonce and
     * a second thing that can be blocked, and emitted markup lands wherever the developer
     * put `@wirekitScripts` — inside an `overflow: hidden` wrapper it would be clipped,
     * which is the exact problem teleporting out of the tree exists to solve. The tag is
     * already present, already nonced, and already re-rendered by the server on every
     * page a `wire:navigate` arrives at.
     *
     * Falls back to the English literal in the bundle when the catalog has no entry: a
     * landmark with no accessible name is worse than one named in the wrong language.
     */
    public static function overlayLabelAttribute(): string
    {
        return ' data-wk-overlay-label="'.e(__('wirekit::Overlays')).'"';
    }

    /**
     * The stylesheet tag, decided by the same rule as `scriptTag()`.
     *
     * Both tags decide through `publishedIsStale()` and no directive inlines the rule, so
     * the stylesheet and the scripts cannot disagree about which copy is current.
     *
     * @param  string  $nonceAttr  pre-escaped ` nonce="…"` or an empty string
     */
    public static function styleTag(string $file, string $nonceAttr = ''): string
    {
        $published = public_path('vendor/wirekit/'.$file);
        $dist = self::distPath($file);

        if (self::publishedIsStale($published, $dist)) {
            $version = $dist ? filemtime($dist) : time();
            $src = url('/wirekit/'.$file).'?v='.$version;
        } else {
            $src = asset('vendor/wirekit/'.$file).'?v='.filemtime($published);
        }

        return '<link rel="stylesheet"'.$nonceAttr.' href="'.$src.'">'."\n";
    }

    /**
     * Is the published copy something other than what this package ships?
     *
     * Compared by content, not by modification time. A modification time answers "which
     * file was written later", and that is not the question: Composer writes an upgraded
     * `dist/` with whatever timestamp the archive or the filesystem hands it, and a
     * `vendor:publish` from the previous version can carry a later one. A time comparison
     * would then call the published copy current and serve the old bytes, with a
     * fresh-looking `?v=` stamp and no error anywhere.
     *
     * A missing published file is stale by definition. Beyond that: sizes first, because two
     * files of different length cannot be equal and the check costs one stat; a hash only when
     * the sizes agree, which is the one case where bytes still have to be read.
     *
     * Memoized per request, keyed by the published file's own mtime and size, so the answer
     * self-invalidates the moment anything republishes — rather than by path alone, which
     * would cache a verdict across a publish inside a long-lived worker.
     */
    protected static function publishedIsStale(string $published, ?string $dist): bool
    {
        if (! file_exists($published)) {
            return true;
        }

        // Nothing to compare against. The published copy is all there is, so serve it —
        // returning "stale" here would route to a file the package does not have.
        if ($dist === null || ! is_file($dist)) {
            return false;
        }

        static $memo = [];

        $publishedSize = filesize($published);
        $key = $published.'|'.filemtime($published).'|'.$publishedSize.'|'.$dist;

        if (isset($memo[$key])) {
            return $memo[$key];
        }

        $distSize = filesize($dist);

        return $memo[$key] = $publishedSize !== $distSize
            || hash_file('xxh128', $published) !== hash_file('xxh128', $dist);
    }

    /**
     * Middleware for the asset routes: none by default.
     *
     * The `web` group would add `StartSession` and `AddQueuedCookiesToResponse`.
     * The handlers below read a file off disk: no session, no CSRF token, no auth,
     * no model binding, so the group would give them nothing and cost them two
     * things.
     *
     * The expensive one is a header combination. Every asset answer declares
     * `public, max-age=31536000, immutable`, and `web` added `Set-Cookie` beside
     * it — a response a shared cache may store for a year and hand, cookie
     * included, to the next visitor. Most shared caches refuse a response
     * carrying `Set-Cookie`, but that is a per-vendor DEFAULT, not a property of
     * what we send, and a package cannot ship a header whose safety depends on a
     * stranger's configuration. The cheap one is a session read and write per
     * asset hit, for a file no logged-in state can change.
     *
     * The irony is worth stating once: the year-long directive exists so a CDN
     * can serve these files, and the cookie is exactly what makes a CDN decline
     * them. The header promised what the cookie prevented.
     *
     * It is configurable rather than fixed, because an application that hangs its
     * own security headers or HTTPS enforcement in `web` needs a way to restore
     * them for these asset routes short of forking. `wirekit.assets.middleware` is
     * that way, and the empty default is what keeps the response cacheable.
     *
     * @return array<int, string>
     */
    protected function assetRouteMiddleware(): array
    {
        // A bare string is the shape a developer reaches for first. Casting it
        // rather than ignoring it keeps `'middleware' => 'web'` from silently
        // producing a route with no middleware at all.
        return array_values((array) config('wirekit.assets.middleware', []));
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
        // Map route paths to dist/ files with their MIME types.
        //
        // `; charset=utf-8` is not decoration on a text type. Without it a
        // browser opening the URL on its own falls back to a legacy single-byte
        // encoding, and every em-dash in the file renders as mojibake — reported
        // from the field against /wirekit/wirekit.css.
        //
        // Only the DIRECT hit is affected, which is why it survived so long: a
        // stylesheet loaded from a page inherits the document's encoding, so
        // every app looked fine and only someone pasting the asset URL into the
        // address bar ever saw it. The font route below has always declared it;
        // the routes below were written first and never caught up.
        $assets = [
            'wirekit/wirekit.css' => ['file' => 'wirekit.css', 'type' => 'text/css; charset=utf-8'],
            'wirekit/wirekit.min.css' => ['file' => 'wirekit.min.css', 'type' => 'text/css; charset=utf-8'],
            'wirekit/wirekit.js' => ['file' => 'wirekit.js', 'type' => 'application/javascript; charset=utf-8'],
            'wirekit/wirekit.core.js' => ['file' => 'wirekit.core.js', 'type' => 'application/javascript; charset=utf-8'],
            'wirekit/wirekit.esm.js' => ['file' => 'wirekit.esm.js', 'type' => 'application/javascript; charset=utf-8'],
            'wirekit/wirekit-apex.js' => ['file' => 'wirekit-apex.js', 'type' => 'application/javascript; charset=utf-8'],
            'wirekit/wirekit-tiptap.js' => ['file' => 'wirekit-tiptap.js', 'type' => 'application/javascript; charset=utf-8'],
            'wirekit/wirekit-optimistic.js' => ['file' => 'wirekit-optimistic.js', 'type' => 'application/javascript; charset=utf-8'],
            'wirekit/wirekit-theme.js' => ['file' => 'wirekit-theme.js', 'type' => 'application/javascript; charset=utf-8'],
            'wirekit/wirekit-alpine.js' => ['file' => 'wirekit-alpine.js', 'type' => 'application/javascript; charset=utf-8'],
            'wirekit/wirekit-alpine.csp.js' => ['file' => 'wirekit-alpine.csp.js', 'type' => 'application/javascript; charset=utf-8'],
        ];

        // Bundled fonts, served straight from the package when they were never
        // published. Without this the fonts component emitted no
        // <link> at all for a configured-but-unpublished family, and the page
        // fell back to system fonts — a difference nobody notices in review and
        // everybody notices in production, because the app looked right locally
        // where the publish had been run once by hand.
        //
        // The route serves the family's whole directory: the CSS and the woff2
        // files it @font-face-references, which are siblings of it.
        Route::group(['middleware' => $this->assetRouteMiddleware()], function (): void {
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

                $body = file_get_contents($file);

                // The static file ships `swap`; `wirekit.fonts.display` is what
                // this application asked for. Substituting here is what makes the
                // config a switch rather than a record on this path — and the
                // component puts the value in the cache-busting query string, so
                // changing the config produces a new URL instead of a year-long
                // immutable cache of the previous answer.
                if ($type === 'text/css; charset=utf-8') {
                    $body = FontCss::applyDisplay($body);
                }

                return response($body, 200, [
                    'Content-Type' => $type,
                    // Same one-year immutable policy as the other assets. The URL
                    // carries a ?v={filemtime} from the component, so new content
                    // is a new URL.
                    'Cache-Control' => 'public, max-age=31536000, immutable',
                ]);
            })->where('path', '.*');
        });

        // Flags served from this package's own `resources/flags`, so the flag component works with
        // nothing installed beyond WireKit itself. Only a flag the manifest names is served: the
        // code and the format are looked up, never joined into a path unchecked, so the route
        // cannot be walked to anything else in the package.
        //
        // A browser that opens this URL directly treats the SVG as a document. The response
        // therefore carries a policy that allows no script, no request and no plugin, plus
        // nosniff. The artwork is checked for active content before the package ships; these
        // headers are what still holds if a file ever were not.
        Route::group(['middleware' => $this->assetRouteMiddleware()], function (): void {
            Route::get('wirekit/flags/{format}/{code}.svg', function (string $format, string $code) {
                $file = FlagPackage::file($code, $format);

                if ($file === null) {
                    abort(404);
                }

                return response((string) file_get_contents($file), 200, [
                    'Content-Type' => 'image/svg+xml',
                    'X-Content-Type-Options' => 'nosniff',
                    'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
                    // The component keys the URL with this file's own checksum, so a changed flag
                    // is a new URL and the year-long promise holds.
                    'Cache-Control' => 'public, max-age=31536000, immutable',
                ]);
            })->where(['format' => '4x3|1x1', 'code' => '[a-z]{2,6}(?:-[a-z]{2,3})?']);
        });

        Route::group(['middleware' => $this->assetRouteMiddleware()], function () use ($assets): void {
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
     * `components.<name>.<option>`, that is the difference between every usable
     * component section and the handful that file happened to carry.
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
