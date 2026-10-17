{{-- optimistic-ui: n/a — navigation
     Two links, to the label and to the product information sheet, and no action whose result
     could be shown early. --}}
@props([
    // The product's energy efficiency class as its label shows it: `A` to `G`, or a class of an
    // older scale such as `A+++`.
    'efficiency' => null,
    // The scale of the label, best class first: `A-G` for the labels in force since 2021, or an
    // older seven-class scale, `A+++-D`, `A++-E` or `A+-F`.
    'scale' => 'A-G',
    // The address of the label itself, an image or a PDF. The arrow links to it, which is how the
    // labeling acts let a shop show the label behind the arrow: on a click, a hover or a tap.
    'href' => null,
    // The address of the product information sheet, the product's page in the EU product database
    // for instance. Linked beside the arrow under the name the acts prescribe.
    'sheet' => null,
    // The size of the price beside it, as on `price`: `xs`, `sm`, `md`, `lg` or `xl`. The class
    // on the arrow stands in that size.
    'size' => 'md',
    // `right` points the arrow right with the range on its left; `left` mirrors it.
    'direction' => 'right',
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
    \Pushery\WireKit\WireKit::warnUnknownProps('energy-label', $attributes->getAttributes());

    $newTab = BooleanProp::from($newTab, false);

    // The seven-class scales, best class first, each named by its two ends. The seven colors run
    // from the best class to the worst, so a class takes the color of its place on its scale.
    $scales = [
        'A-G' => ['A', 'B', 'C', 'D', 'E', 'F', 'G'],
        'A+++-D' => ['A+++', 'A++', 'A+', 'A', 'B', 'C', 'D'],
        'A++-E' => ['A++', 'A+', 'A', 'B', 'C', 'D', 'E'],
        'A+-F' => ['A+', 'A', 'B', 'C', 'D', 'E', 'F'],
    ];

    // A scale or a class the label does not have is reported, and the arrow is then left out
    // rather than drawn with a stand-in: an arrow that names a class the product does not have
    // tells a buyer something false about it.
    // A scale is named by its two ends. The dash between them is often the en dash a label prints
    // (A–G) or another dash from Unicode, and each of them reads as the hyphen here, as does a space
    // around it: a value that looks right then also draws its arrow.
    $scaleKey = (string) preg_replace(['/\s+/u', '/[\x{2010}-\x{2015}\x{2212}\x{FE58}\x{FE63}\x{FF0D}]/u'], ['', '-'], (string) $scale);
    $scale = StrictnessGate::enforce('energy-label', 'scale', strtoupper($scaleKey), array_keys($scales), '');
    $classes = $scales[$scale] ?? [];
    $efficiency = $classes === []
        ? ''
        : StrictnessGate::enforce('energy-label', 'efficiency', strtoupper(trim((string) $efficiency)), $classes, '');
    $step = $efficiency === '' ? null : array_flip($classes)[$efficiency] + 1;

    $direction = WireKit::validateProp('energy-label', 'direction', (string) $direction, ['right', 'left']);

    // The size of the price, so the class stands as large as the price does. Literal arms, as on
    // `price`: an assembled class has no rule behind it.
    $sizeClasses = match (WireKit::validateProp('energy-label', 'size', (string) $size, ['xs', 'sm', 'md', 'lg', 'xl'])) {
        'xs' => 'text-[length:var(--text-wk-xs)]',
        'sm' => 'text-[length:var(--text-wk-sm)]',
        'md' => 'text-[length:var(--text-wk-md)]',
        'lg' => 'text-[length:var(--text-wk-lg)]',
        'xl' => 'text-[length:var(--text-wk-xl)]',
    };

    $rootClasses = WireKit::resolveClasses('energy-label', 'base', 'inline-flex flex-wrap items-center gap-x-[var(--gap-wk-sm)] gap-y-[var(--gap-wk-xs)]', $scope);
    // On a touch screen the arrow takes the kit's 44px target as an area that paints nothing. The
    // marker stands in front of the block, so a personalization of the block keeps it.
    $arrowLinkClasses = 'wk-touch-target '.WireKit::resolveClasses('energy-label', 'arrow', 'inline-flex', $scope);

    $statement = $step === null ? '' : __('wirekit::Energy efficiency class :efficiency on a scale from :best to :worst.', [
        'efficiency' => $efficiency,
        'best' => $classes[0],
        'worst' => $classes[6],
    ]);
    // Both addresses are a product's data, from a supplier's feed or the EU product database more
    // often than written in a view. One the browser would run as script, `javascript:` and the
    // like, leaves the label without its link and the sheet out, as an address never given.
    $href = filled($href) ? \Pushery\WireKit\Support\SafeUrl::href(\Pushery\WireKit\Support\UrlProp::text($href)) : '';
    $sheet = filled($sheet) ? \Pushery\WireKit\Support\SafeUrl::href(\Pushery\WireKit\Support\UrlProp::text($sheet)) : '';
    $target = $newTab ? '_blank' : null;
@endphp

@if($step !== null)
@php($energyImage = ['energyClasses' => $classes, 'energyClass' => $efficiency, 'energyStep' => $step, 'energyDirection' => $direction, 'energySize' => $sizeClasses])
<span data-wk-prose-skip data-wk-energy-label {{ $attributes->class([$rootClasses]) }}>
    @if(filled($href))
        {{-- The arrow links to the label, which the acts let a shop show this way. --}}
        <x-wirekit::link :href="$href" underline="none" :target="$target" class="{{ $arrowLinkClasses }}">@include('wirekit::components.partials.energy-label-image', $energyImage)<span class="sr-only">{{ $statement }} {{ __('wirekit::Show the energy label') }}</span></x-wirekit::link>
    @else
        <span class="inline-flex">@include('wirekit::components.partials.energy-label-image', $energyImage)<span class="sr-only">{{ $statement }}</span></span>
    @endif
    @if(filled($sheet))
        <x-wirekit::link :href="$sheet" :target="$target" size="sm">{{ __('wirekit::Product information sheet') }}</x-wirekit::link>
    @endif
</span>
@endif
