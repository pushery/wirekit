{{-- optimistic-ui: n/a — navigation
     Links to the label and to the product information sheet, and no action whose result could be
     shown early. --}}
@props([
    // The fuel efficiency class on the tire's label, `A` to `E`.
    'fuel' => null,
    // The wet grip class on the tire's label, `A` to `E`.
    'wetGrip' => null,
    // The external rolling noise class on the tire's label, `A` to `C`.
    'noise' => null,
    // The external rolling noise in decibels, shown after the loudspeaker.
    'noiseDb' => null,
    // The address of the label itself, an image or a PDF. Each arrow links to it, which is how a
    // shop shows the label behind the arrows: on a click, a hover or a tap.
    'href' => null,
    // The address of the product information sheet, the tire's page in the EU product database for
    // instance. Linked beside the arrows under the name the regulation prescribes.
    'sheet' => null,
    // The size of the price beside it, as on `price`: `xs`, `sm`, `md`, `lg` or `xl`. The classes
    // on the arrows stand in that size.
    'size' => 'md',
    // Opens the label and the sheet in another tab, for a page that must not be left.
    'newTab' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\Support\StrictnessGate;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('tire-label', $attributes->getAttributes());

    $newTab = BooleanProp::from($newTab, false, 'tire-label', 'newTab');

    // The three parameters of the label in its order, each with its scale, best class first. A
    // class the scale does not have is reported, and that arrow is left out rather than drawn with
    // a stand-in: an arrow that names a class the tire does not have tells a buyer something false.
    $parameters = [];

    foreach ([
        'fuel' => ['fuel', $fuel, ['A', 'B', 'C', 'D', 'E']],
        'grip' => ['wet-grip', $wetGrip, ['A', 'B', 'C', 'D', 'E']],
        'noise' => ['noise', $noise, ['A', 'B', 'C']],
    ] as $parameter => [$prop, $value, $classes]) {
        if (blank($value)) {
            continue;
        }

        $class = StrictnessGate::enforce('tire-label', $prop, strtoupper(trim((string) $value)), $classes, '');

        if ($class === '') {
            continue;
        }

        $parameters[$parameter] = [
            'classes' => $classes,
            'class' => $class,
            'step' => array_search($class, $classes, true) + 1,
        ];
    }

    // A tire label without any class has nothing to show, which is reported like a wrong class.
    if (blank($fuel) && blank($wetGrip) && blank($noise)) {
        StrictnessGate::reject('tire-label', 'classes', '', 'a class for fuel, wet-grip or noise', '');
    }

    // Decibels are a whole number on the label; anything else is reported and left out, INF and
    // NAN included, which no integer holds.
    if (blank($noiseDb)) {
        $noiseDb = null;
    } elseif (is_numeric($noiseDb) && (float) $noiseDb > 0 && ($decibels = \Pushery\WireKit\Support\NumericProp::intOrNull(round((float) $noiseDb))) !== null) {
        $noiseDb = $decibels;
    } else {
        StrictnessGate::reject('tire-label', 'noise-db', is_scalar($noiseDb) ? \Pushery\WireKit\Support\NumericProp::text($noiseDb) : get_debug_type($noiseDb), 'a number of decibels', '');
        $noiseDb = null;
    }

    // The size of the price, so the classes stand as large as the price does. Literal arms, as on
    // `price`: an assembled class has no rule behind it.
    $sizeClasses = match (WireKit::validateProp('tire-label', 'size', (string) $size, ['xs', 'sm', 'md', 'lg', 'xl'])) {
        'xs' => 'text-[length:var(--text-wk-xs)]',
        'sm' => 'text-[length:var(--text-wk-sm)]',
        'md' => 'text-[length:var(--text-wk-md)]',
        'lg' => 'text-[length:var(--text-wk-lg)]',
        'xl' => 'text-[length:var(--text-wk-xl)]',
    };

    $rootClasses = WireKit::resolveClasses('tire-label', 'base', 'inline-flex flex-wrap items-center gap-x-[var(--gap-wk-sm)] gap-y-[var(--gap-wk-xs)]', $scope);
    // On a touch screen each arrow takes the kit's 44px target as an area that paints nothing. The
    // marker stands in front of the block, so a personalization of the block keeps it.
    $arrowLinkClasses = 'wk-touch-target '.WireKit::resolveClasses('tire-label', 'arrow', 'inline-flex', $scope);

    // The words the regulation gives each arrow, followed by the class and the range.
    $statements = [];

    foreach ($parameters as $parameter => $arrow) {
        $words = [
            'class' => $arrow['class'],
            'best' => $arrow['classes'][0],
            'worst' => $arrow['classes'][count($arrow['classes']) - 1],
        ];

        $statements[$parameter] = match ($parameter) {
            'fuel' => __('wirekit::Fuel efficiency class is :class (range :best-:worst).', $words),
            'grip' => __('wirekit::Wet grip class is :class (range :best-:worst).', $words),
            'noise' => $noiseDb === null
                ? __('wirekit::External rolling noise class is :class (range :best-:worst).', $words)
                : __('wirekit::External rolling noise class is :class (range :best-:worst), :db dB.', $words + ['db' => $noiseDb]),
        };
    }

    // Both addresses are a product's data, from a supplier's feed or the EU product database more
    // often than written in a view. One the browser would run as script, `javascript:` and the
    // like, leaves the label without its link and the sheet out, as an address never given.
    $href = filled($href) ? \Pushery\WireKit\Support\SafeUrl::href(\Pushery\WireKit\Support\UrlProp::text($href)) : '';
    $sheet = filled($sheet) ? \Pushery\WireKit\Support\SafeUrl::href(\Pushery\WireKit\Support\UrlProp::text($sheet)) : '';
    $target = $newTab ? '_blank' : null;
@endphp

@if($parameters !== [])
<span data-wk-prose-skip data-wk-tire-label data-wk-sr-host {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$rootClasses])) }}>
    @foreach($parameters as $parameter => $arrow)
        @php($tireImage = ['tireParameter' => $parameter, 'tireClasses' => $arrow['classes'], 'tireClass' => $arrow['class'], 'tireStep' => $arrow['step'], 'tireSize' => $sizeClasses, 'tireDecibels' => $parameter === 'noise' ? $noiseDb : null])
        @if(filled($href))
            {{-- Each arrow links to the label, the nested display the regulation lets a shop use. --}}
            <x-wirekit::link :href="$href" underline="none" :target="$target" class="{{ $arrowLinkClasses }}">@include('wirekit::components.partials.tire-label-image', $tireImage)<span class="sr-only">{{ $statements[$parameter] }} {{ __('wirekit::Show the tire label') }}</span></x-wirekit::link>
        @else
            <span class="inline-flex">@include('wirekit::components.partials.tire-label-image', $tireImage)<span class="sr-only">{{ $statements[$parameter] }}</span></span>
        @endif
    @endforeach
    @if(filled($sheet))
        <x-wirekit::link :href="$sheet" :target="$target" size="sm">{{ __('wirekit::Product information sheet') }}</x-wirekit::link>
    @endif
</span>
@endif
