@props([
    // Comfortable padding by default. Set padded=false for edge-to-edge
    // content (a flush hero image, a full-bleed table). When false, the body
    // also tags itself so a raw <table> dropped inside still gets readable
    // cell padding (see the .wk-card-body[data-wk-padded="false"] rule in
    // dist/wirekit.css).
    'padded' => true,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $padded = BooleanProp::from($padded, true);

    // Body section — main content area. `wk-card-body` is the marker the
    // table-aware padding rule keys off; the padding utilities drop out when
    // padded=false.
    $classes = WireKit::resolveClasses('card.body', 'base', implode(' ', array_filter([
        'wk-card-body',
        $padded ? 'px-[var(--padding-wk-x-lg)]' : null,
        $padded ? 'py-[var(--padding-wk-y-lg)]' : null,
        'font-[family-name:var(--font-wk-sans)]',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text)]',
    ])), $scope);
@endphp

<div {{ $attributes->class([$classes]) }} data-wk-padded="{{ $padded ? 'true' : 'false' }}">
    {{ $slot }}
</div>
