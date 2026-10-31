{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    // The value's color: a hex color, rgb(), hsl() or oklch(). It is written into the swatch's
    // style, so any other value is rejected and the swatch draws no fill.
    'color' => null,
    // A picture of the value, such as a fabric or a wood grain, that fills the swatch. Given with a
    // `color`, the color lies behind it: it shows through the picture's transparent parts, while
    // the picture loads and where it does not load. An address with a scheme other than http or
    // https draws nothing.
    'image' => null,
    // The value's name. It names the swatch for assistive technology, or stands beside it as text
    // with `showLabel`, and a pill draws it as its text. Without a name the swatch is decorative and
    // hidden from assistive technology.
    'label' => null,
    // Show the name as text beside the swatch. A pill shows it inside already.
    'showLabel' => false,
    // A line across the swatch, or through a pill's text, and "unavailable" read with its name.
    'unavailable' => false,
    // `sm` (24px), `md` (32px) or `lg` (40px), the height of a pill.
    'size' => config('wirekit.components.swatch.size', 'md'),
    // `circle`, `square`, or `pill`, which draws the name as text, for a value a name says best,
    // such as a size. A color or a picture given with a pill is a dot before the name.
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
    $showLabel = BooleanProp::from($showLabel, false, 'swatch', 'showLabel');
    $unavailable = BooleanProp::from($unavailable, false, 'swatch', 'unavailable');

    $size = WireKit::validateProp('swatch', 'size', (string) $size, ['sm', 'md', 'lg']);
    $shape = WireKit::validateProp('swatch', 'shape', (string) $shape, ['circle', 'square', 'pill']);
    $label = \Pushery\WireKit\Support\SlotContent::textOf($label);

    // A pill draws the name as its text, and without a name it has nothing to draw: it is reported
    // and drawn as a circle, which a color or a picture can fill.
    if ($shape === 'pill' && $label === '') {
        StrictnessGate::reject('swatch', 'label', '', 'a name, which a pill draws as its text', '');
        $shape = 'circle';
    }

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

    // A background image rather than an <img> or an SVG image: a picture that does not load draws
    // nothing over the color behind it, where Blink draws its mark for a broken picture over an
    // <img>, and stretches it over an SVG image that covers the swatch (both measured). The address
    // is CSS in a style attribute, so Support\CssUrl writes it as a quoted string that nothing in
    // it can end. Its scheme is checked as every loaded URL is.
    $picture = '';

    if (filled($image)) {
        $picture = SafeUrl::href(\Pushery\WireKit\Support\UrlProp::text($image), SafeUrl::LOAD_SCHEMES);

        if ($picture === '') {
            StrictnessGate::reject('swatch', 'image', (string) $image, 'an http or https address, or a path', '');
        }
    }

    // The fill's color, and the picture over it, as one list of declarations.
    $fillStyle = implode('; ', array_filter([
        $fill !== '' ? 'background-color: '.$fill : null,
        $picture !== '' ? 'background-image: '.\Pushery\WireKit\Support\CssUrl::of($picture) : null,
    ]));

    $name = $label === '' ? '' : ($unavailable ? __('wirekit:::label, unavailable', ['label' => $label]) : $label);

    $classes = WireKit::resolveClasses('swatch', 'base', implode(' ', [
        // The size again as a property, which the text of a checkbox or a radio reads to keep a
        // swatch within its line (dist/wirekit.css, `.wk-choice-text [data-wk-swatch]`). A pill is
        // as tall as a circle of its size and at least as wide, so its target is never smaller.
        ...($shape === 'pill' ? [
            'relative inline-flex shrink-0 items-center justify-center gap-[var(--gap-wk-xs)] align-middle whitespace-nowrap leading-none',
            match ($size) {
                'sm' => 'h-[var(--size-wk-xs)] min-w-[var(--size-wk-xs)] px-[var(--padding-wk-x-xs)] text-[length:var(--text-wk-xs)] [--wk-swatch-size:var(--size-wk-xs)]',
                'lg' => 'h-[var(--size-wk-md)] min-w-[var(--size-wk-md)] px-[var(--padding-wk-x-md)] text-[length:var(--text-wk-md)] [--wk-swatch-size:var(--size-wk-md)]',
                default => 'h-[var(--size-wk-sm)] min-w-[var(--size-wk-sm)] px-[var(--padding-wk-x-sm)] text-[length:var(--text-wk-sm)] [--wk-swatch-size:var(--size-wk-sm)]',
            },
            'rounded-full bg-[var(--color-wk-surface)] text-[color:var(--color-wk-text)]',
        ] : [
            'relative inline-block shrink-0 align-middle',
            match ($size) {
                'sm' => 'size-[var(--size-wk-xs)] [--wk-swatch-size:var(--size-wk-xs)]',
                'lg' => 'size-[var(--size-wk-md)] [--wk-swatch-size:var(--size-wk-md)]',
                default => 'size-[var(--size-wk-sm)] [--wk-swatch-size:var(--size-wk-sm)]',
            },
            $shape === 'square' ? 'rounded-[var(--radius-wk-sm)]' : 'rounded-full',
        ]),
        // The edge of the form controls, which holds 3:1 against the page: a white swatch on a
        // white page keeps its outline. A real border, which a forced-colors theme draws in its own
        // color, as it does an outline a caller sets here.
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-border-strong)]',
    ]), $scope);
@endphp

@if($shape === 'pill')
    {{-- The name is the pill's text and is read as text. An unavailable value's word is read with
         it, and the line goes through the name shown. --}}
    <span data-wk-swatch {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}>
        @if($fill !== '' || $picture !== '')
            {{-- The color or the picture as a dot before the name, in the edge of a swatch, and the
                 content a forced-colors theme keeps, as on a circle. --}}
            <span data-wk-swatch-fill aria-hidden="true" class="relative size-[1em] shrink-0 overflow-hidden rounded-full border-[length:var(--border-wk-width)] border-[var(--color-wk-border-strong)] bg-cover bg-center forced-color-adjust-none" @if($fillStyle !== '') style="{{ $fillStyle }}" @endif>
            </span>
        @endif
        @if($unavailable)
            <span class="sr-only">{{ $name }}</span>
            <span aria-hidden="true" class="line-through">{{ $label }}</span>
        @else
            <span>{{ $label }}</span>
        @endif
    </span>
@else
@if($showLabel && $name !== '')
<span {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, ['relative inline-flex items-center gap-[var(--gap-wk-sm)] align-middle'])) }}>
@endif
    <span
        data-wk-swatch
@if($showLabel && $name !== '')
        aria-hidden="true"
        class="{{ $classes }}"
@elseif($name !== '')
        {{ $attributes->merge(['role' => 'img', 'aria-label' => $name])->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}
@else
        aria-hidden="true"
        {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}
@endif
    >
        {{-- The fill inside the edge. The color is the content, as a photo's would be, so a
             forced-colors theme keeps it here, and the line of an unavailable value with it, while
             the edge around it takes the theme's colors. --}}
        <span data-wk-swatch-fill class="absolute inset-0 overflow-hidden rounded-[inherit] bg-cover bg-center forced-color-adjust-none" @if($fillStyle !== '') style="{{ $fillStyle }}" @endif>
            @if($unavailable)
                {{-- A line in the text color over a wider one in the surface color, so it reads on a
                     light fill and on a dark one. --}}
                <svg data-wk-swatch-unavailable aria-hidden="true" class="absolute inset-0 size-full" viewBox="0 0 20 20" preserveAspectRatio="none" fill="none">
                    <line x1="2" y1="18" x2="18" y2="2" class="stroke-[var(--color-wk-surface)]" stroke-width="4" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                    <line x1="2" y1="18" x2="18" y2="2" class="stroke-[var(--color-wk-text)]" stroke-width="2" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                </svg>
            @endif
        </span>
    </span>@if($showLabel && $name !== '')
    @if($unavailable)
        {{-- Read as one name with the word the line says; shown as the name alone, beside the line. --}}
        <span class="sr-only">{{ $name }}</span>
        <span aria-hidden="true" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)]">{{ $label }}</span>
    @else
        <span class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)]">{{ $label }}</span>
    @endif
</span>@endif
@endif