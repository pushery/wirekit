{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    // The value's color: a hex color, rgb(), hsl() or oklch(). It is written into the swatch's
    // style, so any other value is rejected and the swatch draws no fill.
    'color' => null,
    // A picture of the value in place of a color, such as a fabric or a wood grain, drawn as an
    // <img> that fills the swatch. An address with a scheme other than http or https draws nothing.
    'image' => null,
    // The value's name. It names the swatch for assistive technology, or stands beside it as text
    // with `showLabel`. Without a name the swatch is decorative and hidden from assistive technology.
    'label' => null,
    // Show the name as text beside the swatch.
    'showLabel' => false,
    // A line across the swatch, and "unavailable" read with its name.
    'unavailable' => false,
    // `sm` (24px), `md` (32px) or `lg` (40px).
    'size' => config('wirekit.components.swatch.size', 'md'),
    // `circle` or `square`.
    'shape' => config('wirekit.components.swatch.shape', 'circle'),
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\Support\CssColor;
    use Pushery\WireKit\Support\SafeUrl;
    use Pushery\WireKit\Support\StrictnessGate;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('swatch', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $showLabel = BooleanProp::from($showLabel, false);
    $unavailable = BooleanProp::from($unavailable, false);

    $size = WireKit::validateProp('swatch', 'size', (string) $size, ['sm', 'md', 'lg']);
    $shape = WireKit::validateProp('swatch', 'shape', (string) $shape, ['circle', 'square']);
    $label = trim((string) ($label ?? ''));

    // The color goes into a `style` attribute, where escaping does not stop a `;`: a value that
    // carried further declarations would style the swatch itself. Support\CssColor admits the four
    // forms a color picker reads and nothing that ends a declaration.
    $fill = '';

    if (filled($color)) {
        $fill = CssColor::value((string) $color);

        if ($fill === '') {
            StrictnessGate::reject('swatch', 'color', (string) $color, 'a hex color, rgb(), hsl() or oklch()', '');
        }
    }

    // An <img> rather than a background image: an address in a style attribute is CSS again,
    // while `src` is an attribute Blade escapes. Its scheme is checked as every loaded URL is.
    $picture = '';

    if (filled($image)) {
        $picture = SafeUrl::href(\Pushery\WireKit\Support\UrlProp::text($image), SafeUrl::LOAD_SCHEMES);

        if ($picture === '') {
            StrictnessGate::reject('swatch', 'image', (string) $image, 'an http or https address, or a path', '');
        }
    }

    $name = $label === '' ? '' : ($unavailable ? __('wirekit:::label, unavailable', ['label' => $label]) : $label);

    $classes = WireKit::resolveClasses('swatch', 'base', implode(' ', [
        'relative inline-block shrink-0 align-middle',
        match ($size) {
            'sm' => 'size-[var(--size-wk-xs)]',
            'lg' => 'size-[var(--size-wk-md)]',
            default => 'size-[var(--size-wk-sm)]',
        },
        $shape === 'square' ? 'rounded-[var(--radius-wk-sm)]' : 'rounded-full',
        // The edge of the form controls, which holds 3:1 against the page: a white swatch on a
        // white page keeps its outline. A real border, which a forced-colors theme draws in its own
        // color, as it does an outline a caller sets here.
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-border-strong)]',
    ]), $scope);
@endphp

@if($showLabel && $name !== '')
<span {{ $attributes->class(['relative inline-flex items-center gap-[var(--gap-wk-sm)] align-middle']) }}>
@endif
    <span
        data-wk-swatch
@if($showLabel && $name !== '')
        aria-hidden="true"
        class="{{ $classes }}"
@elseif($name !== '')
        {{ $attributes->merge(['role' => 'img', 'aria-label' => $name])->class([$classes]) }}
@else
        aria-hidden="true"
        {{ $attributes->class([$classes]) }}
@endif
    >
        {{-- The fill inside the edge. The color is the content, as a photo's would be, so a
             forced-colors theme keeps it here, and the line of an unavailable value with it, while
             the edge around it takes the theme's colors. --}}
        <span data-wk-swatch-fill class="absolute inset-0 overflow-hidden rounded-[inherit] forced-color-adjust-none" @if($fill !== '') style="background-color: {{ $fill }}" @endif>
            @if($picture !== '')
                <img data-wk-prose-skip src="{{ $picture }}" alt="" loading="lazy" decoding="async" class="absolute inset-0 size-full object-cover">
            @endif
            @if($unavailable)
                {{-- A line in the text color over a wider one in the surface color, so it reads on a
                     light fill and on a dark one. --}}
                <svg data-wk-swatch-unavailable aria-hidden="true" class="absolute inset-0 size-full" viewBox="0 0 20 20" preserveAspectRatio="none" fill="none">
                    <line x1="2" y1="18" x2="18" y2="2" class="stroke-[var(--color-wk-surface)]" stroke-width="4" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                    <line x1="2" y1="18" x2="18" y2="2" class="stroke-[var(--color-wk-text)]" stroke-width="2" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                </svg>
            @endif
        </span>
    </span>
@if($showLabel && $name !== '')
    @if($unavailable)
        {{-- Read as one name with the word the line says; shown as the name alone, beside the line. --}}
        <span class="sr-only">{{ $name }}</span>
        <span aria-hidden="true" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)]">{{ $label }}</span>
    @else
        <span class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)]">{{ $label }}</span>
    @endif
</span>
@endif