{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    // A CSP nonce for the inline <style> below. Left out, it resolves itself from
    // the container binding or Vite — see WireKit::cspNonce(). Pass one explicitly
    // when the application mints a value per response and publishes it nowhere.
    'nonce' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('fonts', $attributes->getAttributes());

    $wkNonce = $nonce ?? \Pushery\WireKit\WireKit::cspNonce();

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

@php
    // Metric-matched fallbacks for the developer's OWN fonts.
    //
    // Every bundled family ships one of these, generated from measurements. A
    // self-hosted font of the developer's own got nothing — which is exactly the
    // setup the null font values are for, so the capability stopped precisely
    // where the documented path leads.
    //
    // Only the four overrides are emitted, and nothing is invented: a family with
    // no measured numbers has no entry here, because a guessed `size-adjust`
    // moves the layout in the OTHER direction and looks deliberate doing it.
    $customFallbacks = [];

    foreach ((array) ($fontConfig['fallbacks'] ?? []) as $family => $spec) {
        if (! is_array($spec)) {
            continue;
        }

        // Narrowed to what a CSS font-family name may contain, rather than escaped.
        //
        // This string reaches a `<style>` block, so the sink is CSS rather than HTML and
        // Blade's own escaping is the wrong tool — it would turn a quote into `&#039;`
        // and produce a rule the browser drops. Escaping would also leave the question
        // open; narrowing answers it: after this, a name cannot carry a quote, a brace
        // or a semicolon at all, so there is nothing left to break out of.
        //
        // The value comes from the application's own config and is not user input. That
        // is the argument for trusting it, and it is exactly the argument that stops
        // being true the day someone builds this array from a database.
        $locals = array_values(array_filter(array_map(
            static fn ($name): string => trim((string) preg_replace('/[^A-Za-z0-9 _-]/', '', (string) $name)),
            (array) ($spec['local'] ?? [])
        )));

        if ($locals === []) {
            continue;
        }

        $customFallbacks[] = [
            'family' => (string) $family,
            'src' => implode(', ', array_map(static fn (string $n): string => "local('".$n."')", $locals)),
            'overrides' => array_filter([
                'size-adjust' => $spec['sizeAdjust'] ?? null,
                'ascent-override' => $spec['ascentOverride'] ?? null,
                'descent-override' => $spec['descentOverride'] ?? null,
                'line-gap-override' => $spec['lineGapOverride'] ?? null,
            ], static fn ($v): bool => $v !== null && $v !== ''),
        ];
    }
@endphp

@foreach($customFallbacks as $fallback)
    {{-- Registers a local system font under "<family> Fallback" with the measured
         metrics of the developer's own face, so the text painted before the swap
         occupies the same box as the text painted after it. --}}
    <style @if($wkNonce) nonce="{{ $wkNonce }}" @endif>
        @font-face {
            font-family: '{{ $fallback['family'] }} Fallback';
            src: {!! $fallback['src'] !!};
            @foreach($fallback['overrides'] as $property => $value)
            {{ $property }}: {{ $value }};
            @endforeach
        }
    </style>
@endforeach

{{-- CSS Custom Properties — one declaration per CONFIGURED category, and nothing
     for the others.

     It used to write all three unconditionally, with a hardcoded stack standing in
     for an unconfigured category. Those stand-ins were SHORTER than what
     `dist/wirekit.css` ships for the same token — `ui-monospace, monospace` against
     the stylesheet's `ui-monospace, 'Fira Code', 'Cascadia Code', monospace`, and
     the sans stack lost Inter and -apple-system. Both are unlayered `:root` at the
     same specificity, so whichever came second won: place this component after
     `@wirekitStyles` — which is what the integration page's own ordering leads to —
     and the monospace stack silently loses two families. Nothing throws, the HTML is
     identical either way, and it shows only to a reader who has those fonts
     installed. A consuming project found it by reading the computed cascade.

     A declaration whose only job is to restate the shipped default has no job. Now
     an unconfigured category emits nothing and the stylesheet's own value stands,
     from any position.

     The nonce is what keeps this block alive once an application drops
     'unsafe-inline' from style-src: from CSP Level 2 on, a nonce anywhere in a
     directive makes the browser ignore 'unsafe-inline' in that same directive, so
     there is no gradual transition — the block is either nonced or discarded. The
     discard is silent: the page renders and the typography falls back to the
     system font, which passes every HTML comparison and every header assertion. --}}
<style @if($wkNonce) nonce="{{ $wkNonce }}" @endif>
    :root {
        @if($sansPreset)--font-wk-sans: {!! $sansPreset->fontFamily() !!};@endif
        {{-- Serif is the exception, and it is a fact about the stylesheet rather
             than a choice: `dist/wirekit.css` declares `--font-wk-sans` and
             `--font-wk-mono` and NOT `--font-wk-serif`. Omitting the other two
             lets a better value stand; omitting this one would leave the token
             undefined and drop every serif surface to the browser default.
             `FontSystemTest` pins exactly that split, so if the stylesheet ever
             gains a serif declaration the test says to delete this line. --}}
        --font-wk-serif: {!! $serifPreset ? $serifPreset->fontFamily() : 'ui-serif, Georgia, serif' !!};
        @if($monoPreset)--font-wk-mono: {!! $monoPreset->fontFamily() !!};@endif
    }
</style>
