<?php

declare(strict_types=1);

namespace Pushery\WireKit;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\ComponentAttributeBag;
use Livewire\Mechanisms\HandleComponents\HandleComponents;
use Livewire\Mechanisms\HandleSynths\HandleSynths;
use Pushery\WireKit\Charts\ChartManager;
use Pushery\WireKit\Components\Chart;
use Pushery\WireKit\Fonts\FontCss;
use Pushery\WireKit\Fonts\FontRegistry;
use Pushery\WireKit\Icons\IconResolver;
use Pushery\WireKit\Support\AssetTags;
use Pushery\WireKit\Support\BaseLocaleJsonLoader;
use Pushery\WireKit\Support\BlankName;
use Pushery\WireKit\Support\BooleanProp;
use Pushery\WireKit\Support\CallerAttribute;
use Pushery\WireKit\Support\CompactClassAttributes;
use Pushery\WireKit\Support\CompactClasses;
use Pushery\WireKit\Support\ContainedFile;
use Pushery\WireKit\Support\DomId;
use Pushery\WireKit\Support\FaqCollector;
use Pushery\WireKit\Support\FlagPackage;
use Pushery\WireKit\Support\LogThrottle;
use Pushery\WireKit\Support\PropText;
use Pushery\WireKit\Support\SharedViewData;
use Pushery\WireKit\Support\StrictnessGate;
use Pushery\WireKit\Support\TourStepCounter;
use Pushery\WireKit\Support\UnboundModel;
use Pushery\WireKit\Support\ValuelessDirectives;
use Pushery\WireKit\Values\DateRangeSynth;

class WireKitServiceProvider extends ServiceProvider
{
    /** The directory of the anonymous components, as the compiler is given it. */
    private const COMPONENTS_PATH = __DIR__.'/../resources/views/components';

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
                    BooleanProp::from($this->app['config']->get('wirekit.translations.legacy_key_bridge', true), true),
                ),
            );
        }

        // ── The component composer runs first ──
        // boot() registers the composer that reads every component view's data as its caller
        // wrote it, and Laravel runs the composers of a view pattern by pattern, in the order each
        // pattern was first registered. A composer another provider registers for `*` before that
        // would run first, and the component composer would take what it wrote for what the
        // caller wrote. The patterns are taken here, before any provider boots.
        $prefix = $this->app['config']->get('wirekit.prefix', 'wirekit');
        self::reserveComponentComposerPatterns($this->app->make('events'), is_string($prefix) ? $prefix : null);
    }

    /**
     * The view patterns the component composer listens on: the views the class components render
     * under `wirekit::components`, and the anonymous ones under the namespace the compiler derives
     * from the prefix, as `BladeCompiler::anonymousComponentPath()` derives it.
     *
     * @return list<string>
     */
    public static function componentComposerPatterns(?string $prefix): array
    {
        return ['wirekit::components.*', hash('xxh128', $prefix ?: self::COMPONENTS_PATH).'::*'];
    }

    /**
     * Take the component composer's patterns in the event table, each with a listener that does
     * nothing. The composer boot() registers under the same patterns then runs ahead of every
     * composer registered for `*` once this has run.
     */
    public static function reserveComponentComposerPatterns(Dispatcher $events, ?string $prefix): void
    {
        foreach (self::componentComposerPatterns($prefix) as $pattern) {
            $events->listen('composing: '.$pattern, static function (): void {});
        }
    }

    public function boot(): void
    {
        // Reset the per-render registries after each request and before each queued job: DOM
        // ids start clean, a rejected prop value is reported again, tour steps count from zero,
        // a question an orphaned faq-item left behind reaches no later FAQ, and the compact switch
        // is read again. The registries
        // are static, and static state lasts as long as the PHP script that holds it: FPM starts
        // the script afresh for every HTTP request, so they start empty there anyway, while an
        // Octane worker and a queue worker run one script across many requests or jobs. A queue
        // worker never finishes an HTTP request, hence the second listener; WireKit::flush()
        // covers tests.
        $resetPerRender = static function (): void {
            DomId::reset();
            StrictnessGate::forgetLogged();
            CompactClasses::forget();
            TourStepCounter::reset();
            FaqCollector::reset();
        };
        $this->app['events']->listen(RequestHandled::class, $resetPerRender);
        $this->app['events']->listen(JobProcessing::class, $resetPerRender);

        // A Livewire property typed with a date range binds with `wire:model` (Values\DateRangeSynth).
        // Added to Livewire's synthesizers whenever Livewire builds them, so the order in which the
        // two packages register does not matter. A Livewire without HandleSynths, 4.0 to 4.3.0 among
        // the versions composer.json allows, keeps its synthesizers on HandleComponents and never
        // builds HandleSynths, so there the synthesizer goes onto HandleComponents instead.
        $this->callAfterResolving(HandleSynths::class, static function (HandleSynths $synths): void {
            $synths->registerSynth(DateRangeSynth::class);
        });
        $this->callAfterResolving(HandleComponents::class, static function (HandleComponents $components): void {
            if (! class_exists(HandleSynths::class) && method_exists($components, 'registerPropertySynthesizer')) {
                $components->registerPropertySynthesizer(DateRangeSynth::class);
            }
        });

        // ── Publishable assets (FIRST — must register before anything that could fail) ──
        // Registered early so vendor:publish always works, even if later steps throw.
        if ($this->app->runningInConsole()) {
            // Register the artisan commands listed in resources/catalog/commands.php.
            $this->commands(require __DIR__.'/../resources/catalog/commands.php');

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
            // What the published copy is: an inert REFERENCE, not a load path.
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

            // The files in dist/ an application loads, listed in resources/catalog/dist-files.php.
            // `wirekit-scripts` publishes the scripts, which a web server then serves instead of
            // the PHP route; `wirekit-assets` publishes every file at once, the stylesheets
            // included.
            $distFiles = require __DIR__.'/../resources/catalog/dist-files.php';
            $publishedAs = static fn (array $files): array => array_combine(
                array_map(static fn (string $file): string => __DIR__.'/../dist/'.$file, $files),
                array_map(static fn (string $file): string => public_path('vendor/wirekit/'.$file), $files),
            );

            $this->publishes($publishedAs(array_keys(array_filter(
                $distFiles,
                static fn (string $type): bool => str_starts_with($type, 'application/javascript'),
            ))), 'wirekit-scripts');
            $this->publishes($publishedAs(array_keys($distFiles)), 'wirekit-assets');
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
            $componentsPath = self::COMPONENTS_PATH;
            $blade->anonymousComponentPath($componentsPath, $prefix);

            // The class lists of the kit's own views go through the compact table at render time
            // (Support\CompactClassAttributes). A view of the application, a published copy and an
            // inline template are compiled as they are. The check reads the file back because the
            // compiler keeps the path of the last file it compiled, and `compileString()` called on
            // its own would otherwise be taken for that file.
            $viewsRoot = (realpath(__DIR__.'/../resources/views') ?: __DIR__.'/../resources/views').DIRECTORY_SEPARATOR;
            $blade->prepareStringsForCompilationUsing(static function (string $template) use ($blade, $viewsRoot): string {
                $path = (string) $blade->getPath();
                $real = $path === '' ? false : realpath($path);

                return $real !== false && str_starts_with($real, $viewsRoot) && file_get_contents($real) === $template
                    ? CompactClassAttributes::rewrite($template)
                    : $template;
            });

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

            // The same composer drops every attribute the caller bound to `null`, which Blade prints
            // as nothing, and a name bound to `false` or given as a blank text, from the bag and from
            // the view's data, where a prop of that name reads it (Support\CallerAttribute). It
            // removes a caller's `x-model` from a component whose own Alpine root takes the caller's
            // attributes and holds no value to bind (Support\UnboundModel), reads every prop the
            // caller wrote on the tag as the text it stands for, so a value echoed into the tag is
            // escaped once, as a bound one is (Support\PropText), gives a component that names
            // itself its own name back when it is handed a blank one (Support\BlankName), and gives a
            // prop the caller left out its default rather than a value the application shares with
            // every view (Support\SharedViewData).
            $this->app->make('view')->composer($namespaces, static function (View $view): void {
                PropText::normalize($view);
                BlankName::normalize($view);

                $attributes = $view->getData()['attributes'] ?? null;

                if ($attributes instanceof ComponentAttributeBag) {
                    $data = $view->getData();

                    foreach (CallerAttribute::normalize($attributes) as $name => $value) {
                        $camel = Str::camel((string) $name);

                        // Only the copy the tag made of that attribute: what a class component
                        // hands its view under the same name is not the caller's attribute.
                        if (array_key_exists($camel, $data) && $data[$camel] === $value) {
                            $view->with($camel, null);
                        }
                    }

                    ValuelessDirectives::normalize($attributes);
                    UnboundModel::dropFromView($view->name(), $attributes);
                }

                // Last, so a prop the steps above gave as null counts as one the caller wrote.
                SharedViewData::shadow($view);
                SharedViewData::remember($view);
            });

            // A composer the application registers for every view runs after the one above and
            // writes the view's own data, so the view gets back the data the one above left once
            // the application's composers ran (Support\SharedViewData::restore()).
            // Laravel runs the composers of one pattern together, in the order the patterns were
            // first registered, so this one takes patterns of its own, `**` where the one above has
            // `*`, which match the same views and are registered after the application booted.
            $this->app->booted(function () use ($namespaces): void {
                $this->app->make('view')->composer(
                    array_map(static fn (string $pattern): string => $pattern.'*', $namespaces),
                    static function (View $view): void {
                        SharedViewData::restore($view);
                    },
                );
            });

            // A few files on that path have a class behind them, under the same tag. The markup and
            // the declared props stay in the view, so every reader of `@props` still finds them
            // there. The list, with the reason each class exists, is ComponentRegistry::VIEW_CLASSES;
            // the registry reads it as well, so the data a class hands its view is never taken for
            // a slot.
            foreach (ComponentRegistry::VIEW_CLASSES as $tag => $class) {
                $blade->component($class, $prefix.'::'.$tag);
            }
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
        //      in dist/ (`AssetTags::publishedIsStale()`: the sizes first, a hash when they
        //      agree), the web server serves it directly (fastest path).
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
        // asset route serves with `Cache-Control: public, max-age=31536000,
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

        // @wirekitCompactStyles — the compact sheet alone, for an application that bundles
        // `wirekit.css` itself. It links `wirekit-compact.css` while compact classes are on and
        // writes nothing while they are off, so the sheet follows the switch the views follow,
        // which an import in a build cannot. Takes the same optional CSP nonce as @wirekitStyles.
        Blade::directive('wirekitCompactStyles', function ($expression) {
            $expression = trim($expression);
            $nonceExpr = $expression === '' ? "''" : $expression;

            return '<?php
                $__wk_nonce = '.$nonceExpr.';
                $__wk_nonceAttr = $__wk_nonce ? \' nonce="\' . e($__wk_nonce) . \'"\' : "";
                echo \Pushery\WireKit\WireKitServiceProvider::compactStyleTag($__wk_nonceAttr);
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

        $this->validateConfiguredPresets();
    }

    /**
     * The configured font and icon presets, checked while the application boots.
     *
     * An UNKNOWN font preset key (a typo) is fatal in local, so the developer sees it at once;
     * in production the value falls back to the defaults, since a config typo must never break
     * a deployed page.
     *
     * A KNOWN preset that is not published is not reported here. Every process boots the
     * application, a Composer run's `package:discover` among them, which comes before
     * `wirekit:publish-fonts` in the same run of a deploy and renders no page. It is reported
     * where a page asks for the font: `<x-wirekit::fonts>` serves it through the package route,
     * writes an HTML comment, and logs {@see warnUnpublishedFont()}.
     */
    protected function validateConfiguredPresets(): void
    {
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
     * Log a warning that a known font preset a page asks for was never published, so
     * `<x-wirekit::fonts>` serves it through PHP on every font request instead of as a static
     * file. {@see LogThrottle} lets it through once per preset and hour across requests, so a
     * persistent setup writes one actionable line rather than one per request.
     *
     * Internal: public only so the fonts component can reach it, at the moment a page renders
     * it, which is a PHP visibility necessity rather than an API promise.
     *
     * @internal
     */
    public static function warnUnpublishedFont(string $category, string $presetKey): void
    {
        if (isset(static::$unpublishedFontWarned[$presetKey])) {
            return;
        }

        static::$unpublishedFontWarned[$presetKey] = true;

        if (! LogThrottle::firstAcrossRequests('unpublished-font:'.$presetKey)) {
            return;
        }

        // The words of the comment the component writes into the page beside it.
        Log::warning(
            "WireKit: font preset '{$presetKey}' ({$category}) is served through PHP because it was "
            .'never published. It works, but a static file is faster. Run '
            .'`php artisan wirekit:publish-fonts`.'
        );
    }

    /**
     * The absolute path of a file in the package's dist/ directory, or null when it is missing.
     *
     * This method and the tag builders below are what the asset directives compile to, so they
     * keep their names here; each delegates to {@see AssetTags}, which holds the rules.
     *
     * @param  string  $file  Filename inside dist/ (e.g. "wirekit.css")
     */
    public static function distPath(string $file): ?string
    {
        return AssetTags::distPath($file);
    }

    /** The URL of a dist file with its version query; see {@see AssetTags::source()}. */
    protected static function assetSource(string $file): string
    {
        return AssetTags::source($file);
    }

    /**
     * The `@wirekitThemeScript` tag; see {@see AssetTags::themeScriptTag()}.
     *
     * @param  string  $nonceAttr  pre-escaped ` nonce="…"` or an empty string
     */
    public static function themeScriptTag(string $nonceAttr = ''): string
    {
        return AssetTags::themeScriptTag($nonceAttr);
    }

    /** The theme script's code without its license banner; see {@see AssetTags::themeScriptSource()}. */
    protected static function themeScriptSource(): string
    {
        return AssetTags::themeScriptSource();
    }

    /**
     * One `<script>` tag for a bundle; see {@see AssetTags::scriptTag()}.
     *
     * @param  string  $file  bundle filename, e.g. `wirekit.js`
     * @param  string  $nonceAttr  pre-escaped ` nonce="…"` or an empty string
     * @param  string  $extraAttrs  pre-escaped extra attributes, each with its own leading space
     */
    public static function scriptTag(string $file, string $nonceAttr = '', string $extraAttrs = ''): string
    {
        return AssetTags::scriptTag($file, $nonceAttr, $extraAttrs);
    }

    /** The overlay landmark's translated name as an attribute; see {@see AssetTags::overlayLabelAttribute()}. */
    public static function overlayLabelAttribute(): string
    {
        return AssetTags::overlayLabelAttribute();
    }

    /**
     * The stylesheet tag; see {@see AssetTags::styleTag()}.
     *
     * @param  string  $nonceAttr  pre-escaped ` nonce="…"` or an empty string
     */
    public static function styleTag(string $file, string $nonceAttr = ''): string
    {
        return AssetTags::styleTag($file, $nonceAttr);
    }

    /** The compact sheet's tag while compact classes are on; see {@see AssetTags::compactStyleTag()}. */
    public static function compactStyleTag(string $nonceAttr = ''): string
    {
        return AssetTags::compactStyleTag($nonceAttr);
    }

    /** Whether the published copy differs from the package's; see {@see AssetTags::publishedIsStale()}. */
    protected static function publishedIsStale(string $published, ?string $dist): bool
    {
        return AssetTags::publishedIsStale($published, $dist);
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
        // Route paths to dist/ files with their content types: the same files the publish groups
        // name, from resources/catalog/dist-files.php, which also says why each text type carries
        // its charset.
        $assets = [];

        foreach (require __DIR__.'/../resources/catalog/dist-files.php' as $file => $type) {
            $assets['wirekit/'.$file] = ['file' => $file, 'type' => $type];
        }

        // Bundled fonts, served straight from the package when they were never
        // published. Without this the fonts component would emit no <link> at
        // all for a configured-but-unpublished family, and the page would fall
        // back to system fonts — a difference nobody notices in review and
        // everybody notices in production, because the app looks right locally
        // wherever the publish was run once by hand.
        //
        // The route serves the family's whole directory: the CSS and the woff2
        // files it @font-face-references, which are siblings of it.
        Route::group(['middleware' => $this->assetRouteMiddleware()], function (): void {
            Route::get('wirekit/fonts/{path}', function (string $path) {
                // Path traversal: a resolved path that escapes the font root is
                // refused rather than served, and so is a sibling whose name
                // merely begins with `fonts`.
                $file = ContainedFile::resolve(__DIR__.'/../resources/fonts', $path);

                if ($file === null) {
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
                    // Guard against missing dist files and path traversal. The
                    // file name comes from the map above rather than the request;
                    // the check is the same one the font route makes.
                    $path = ContainedFile::resolve(__DIR__.'/../dist', $meta['file']);

                    if ($path === null) {
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
