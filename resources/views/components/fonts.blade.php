@php
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

    // The URL to load a preset's CSS from: the published copy when it exists,
    // otherwise the package route. Cache-busted by the file's own mtime so a
    // `composer update` invalidates it rather than serving last month's CSS from
    // a one-year immutable cache.
    $fontHref = function (FontPreset $preset): string {
        $published = public_path($preset->publishedCssPath());

        if (file_exists($published)) {
            return asset($preset->publishedCssPath()).'?v='.filemtime($published);
        }

        $source = __DIR__.'/../../fonts/'.$preset->cssFile;
        $version = is_file($source) ? filemtime($source) : time();

        return url('wirekit/fonts/'.$preset->cssFile).'?v='.$version;
    };
@endphp

{{-- Font CSS files — only loaded for activated AND published fonts --}}
@if($sansPreset)
    <link rel="stylesheet" href="{{ $fontHref($sansPreset) }}">
    @if($warnMissing($sansPreset))
    <!-- WireKit: Font '{{ $fontConfig['sans'] }}' is served through PHP because it was never published. It works, but a static file is faster. Run: php artisan wirekit:publish-fonts -->
    @endif
@endif

@if($serifPreset)
    <link rel="stylesheet" href="{{ $fontHref($serifPreset) }}">
    @if($warnMissing($serifPreset))
    <!-- WireKit: Font '{{ $fontConfig['serif'] }}' is served through PHP because it was never published. It works, but a static file is faster. Run: php artisan wirekit:publish-fonts -->
    @endif
@endif

@if($monoPreset)
    <link rel="stylesheet" href="{{ $fontHref($monoPreset) }}">
    @if($warnMissing($monoPreset))
    <!-- WireKit: Font '{{ $fontConfig['mono'] }}' is served through PHP because it was never published. It works, but a static file is faster. Run: php artisan wirekit:publish-fonts -->
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
