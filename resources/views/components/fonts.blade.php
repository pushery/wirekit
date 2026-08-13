{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@php
    use Pushery\WireKit\Fonts\FontCss;
    use Pushery\WireKit\Fonts\FontPreset;
    use Pushery\WireKit\Fonts\FontRegistry;

    $fontConfig = config('wirekit.fonts', []);

    // Resolve configured font presets (null if not configured)
    $sansPreset = ($fontConfig['sans'] ?? null)
        ? FontRegistry::get($fontConfig['sans'])
        : null;
    $serifPreset = ($fontConfig['serif'] ?? null)
        ? FontRegistry::get($fontConfig['serif'])
        : null;
    $monoPreset = ($fontConfig['mono'] ?? null)
        ? FontRegistry::get($fontConfig['mono'])
        : null;

    // Helper: is this font activated but not yet published?
    //
    // The published copy is the fast path — the web server hands it over without
    // touching PHP. But a configured font that was never published used to emit
    // NOTHING, so the page silently fell back to system fonts. That is the worst
    // shape a failure can take: it looks right locally, where someone ran the
    // publish once by hand, and it is wrong in production where nobody did.
    //
    // So an unpublished font now falls back to the package route
    // (`/wirekit/fonts/...`), which reads straight from the installed package and
    // is therefore always correct after `composer update`. The inert HTML comment
    // stays as well, in every environment: the route means the page LOOKS right,
    // and the comment is what tells a developer they are paying for PHP on every
    // font request instead of serving a static file.
    $warnMissing = fn (?FontPreset $preset) => $preset
        && ! file_exists(public_path($preset->publishedCssPath()));

    // The configured font-display, and whether the published copy actually
    // carries it. A plain `vendor:publish` copies the stylesheet verbatim, so it
    // keeps the `swap` the package ships however the config reads — and nothing
    // about that fails: the font still loads, just not the way it was asked to.
    // The comment is the only thing that would ever say so on this path, and it
    // is the same shape the unpublished-font case already uses.
    $fontDisplay = FontCss::display();

    $displayMismatch = function (?FontPreset $preset) use ($fontDisplay): bool {
        if (! $preset) {
            return false;
        }

        $published = public_path($preset->publishedCssPath());

        return file_exists($published)
            && ! FontCss::matchesConfiguredDisplay((string) file_get_contents($published), $fontDisplay);
    };

    // The URL to load a preset's CSS from: the published copy when it exists,
    // otherwise the package route. Cache-busted by the file's own mtime so a
    // `composer update` invalidates it rather than serving last month's CSS from
    // a one-year immutable cache.
    //
    // On the route the font-display joins that cache key, because the route
    // substitutes it while serving: same file, same mtime, different bytes.
    // Without it the immutable cache would keep answering with the previous
    // choice for a year, and the config would look like it did nothing. The
    // published path needs no such suffix — there the value is baked into the
    // file, so a re-publish changes the mtime on its own.
    $fontHref = function (FontPreset $preset) use ($fontDisplay): string {
        $published = public_path($preset->publishedCssPath());

        if (file_exists($published)) {
            return asset($preset->publishedCssPath()).'?v='.filemtime($published);
        }

        $source = __DIR__.'/../../fonts/'.$preset->cssFile;
        $version = is_file($source) ? filemtime($source) : time();

        return url('wirekit/fonts/'.$preset->cssFile).'?v='.$version.'-'.$fontDisplay;
    };
@endphp

{{-- Font CSS files — only loaded for activated AND published fonts --}}
@if($sansPreset)
    <link rel="stylesheet" href="{{ $fontHref($sansPreset) }}">
    @if($warnMissing($sansPreset))
    <!-- WireKit: Font '{{ $fontConfig['sans'] }}' is served through PHP because it was never published. It works, but a static file is faster. Run: php artisan wirekit:publish-fonts -->
    @endif
    @if($displayMismatch($sansPreset))
    <!-- WireKit: Font '{{ $fontConfig['sans'] }}' was published with a different font-display than wirekit.fonts.display ('{{ $fontDisplay }}') asks for. A plain vendor:publish copies the file verbatim. Run: php artisan wirekit:publish-fonts --force -->
    @endif
@endif

@if($serifPreset)
    <link rel="stylesheet" href="{{ $fontHref($serifPreset) }}">
    @if($warnMissing($serifPreset))
    <!-- WireKit: Font '{{ $fontConfig['serif'] }}' is served through PHP because it was never published. It works, but a static file is faster. Run: php artisan wirekit:publish-fonts -->
    @endif
    @if($displayMismatch($serifPreset))
    <!-- WireKit: Font '{{ $fontConfig['serif'] }}' was published with a different font-display than wirekit.fonts.display ('{{ $fontDisplay }}') asks for. A plain vendor:publish copies the file verbatim. Run: php artisan wirekit:publish-fonts --force -->
    @endif
@endif

@if($monoPreset)
    <link rel="stylesheet" href="{{ $fontHref($monoPreset) }}">
    @if($warnMissing($monoPreset))
    <!-- WireKit: Font '{{ $fontConfig['mono'] }}' is served through PHP because it was never published. It works, but a static file is faster. Run: php artisan wirekit:publish-fonts -->
    @endif
    @if($displayMismatch($monoPreset))
    <!-- WireKit: Font '{{ $fontConfig['mono'] }}' was published with a different font-display than wirekit.fonts.display ('{{ $fontDisplay }}') asks for. A plain vendor:publish copies the file verbatim. Run: php artisan wirekit:publish-fonts --force -->
    @endif
@endif

{{-- CSS Custom Properties — always rendered so components can reference them --}}
<style>
    :root {
        --font-wk-sans: {!! $sansPreset ? $sansPreset->fontFamily() : 'ui-sans-serif, system-ui, sans-serif' !!};
        --font-wk-serif: {!! $serifPreset ? $serifPreset->fontFamily() : 'ui-serif, Georgia, serif' !!};
        --font-wk-mono: {!! $monoPreset ? $monoPreset->fontFamily() : 'ui-monospace, monospace' !!};
    }
</style>
