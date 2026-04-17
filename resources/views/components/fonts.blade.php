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

    // Helper: check if a font is activated but not yet published (local env only)
    $warnMissing = fn (?FontPreset $preset) => $preset
        && app()->environment('local')
        && ! file_exists(public_path($preset->publishedCssPath()));
@endphp

{{-- Font CSS files — only loaded for activated AND published fonts --}}
@if($sansPreset && file_exists(public_path($sansPreset->publishedCssPath())))
    <link rel="stylesheet" href="{{ asset($sansPreset->publishedCssPath()) }}">
@elseif($warnMissing($sansPreset))
    <!-- WireKit: Font '{{ $fontConfig['sans'] }}' configured but not published. Run: php artisan vendor:publish --tag=wirekit-fonts -->
@endif

@if($serifPreset && file_exists(public_path($serifPreset->publishedCssPath())))
    <link rel="stylesheet" href="{{ asset($serifPreset->publishedCssPath()) }}">
@elseif($warnMissing($serifPreset))
    <!-- WireKit: Font '{{ $fontConfig['serif'] }}' configured but not published. Run: php artisan vendor:publish --tag=wirekit-fonts -->
@endif

@if($monoPreset && file_exists(public_path($monoPreset->publishedCssPath())))
    <link rel="stylesheet" href="{{ asset($monoPreset->publishedCssPath()) }}">
@elseif($warnMissing($monoPreset))
    <!-- WireKit: Font '{{ $fontConfig['mono'] }}' configured but not published. Run: php artisan vendor:publish --tag=wirekit-fonts -->
@endif

{{-- CSS Custom Properties — always rendered so components can reference them --}}
<style>
    :root {
        --font-wk-sans: {!! $sansPreset ? $sansPreset->fontFamily() : 'ui-sans-serif, system-ui, sans-serif' !!};
        --font-wk-serif: {!! $serifPreset ? $serifPreset->fontFamily() : 'ui-serif, Georgia, serif' !!};
        --font-wk-mono: {!! $monoPreset ? $monoPreset->fontFamily() : 'ui-monospace, monospace' !!};
    }
</style>
