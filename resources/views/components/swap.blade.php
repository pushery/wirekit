@props([
    // An Alpine expression naming the state, e.g. expression="isDark". Use this
    // whenever the swap has to react to something on the client — which is nearly
    // always, since a swap that cannot change is just an icon.
    //
    // It exists because `active` alone CANNOT work here: `active` is resolved in
    // PHP at render time, so the swap would be frozen at whatever the server
    // thought and never move again. That is not a hypothetical — it is the bug
    // this component shipped with for exactly one afternoon.
    'expression' => null,
    // Static state, for a swap the server already knows the answer to and that
    // never changes on the client.
    'active' => false,
    // How the two states trade places.
    //   'fade'   — crossfade (the quiet default)
    //   'rotate' — half a turn, for a sun/moon or a chevron
    //   'flip'   — a card flip
    'effect' => config('wirekit.components.swap.effect', 'fade'),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $effect = WireKit::validateProp('swap', 'effect', $effect, ['fade', 'rotate', 'flip']);

    $effectClass = match ($effect) {
        'rotate' => 'wk-swap-rotate',
        'flip' => 'wk-swap-flip',
        default => 'wk-swap-fade',
    };

    $classes = WireKit::resolveClasses('swap', 'base', implode(' ', [
        'wk-swap',
        $effectClass,
        'relative inline-grid place-items-center',
    ]), $scope);

    // Wrap the expression in parentheses before appending the ternary: an
    // expression like "a || b" would otherwise bind as "a || b ? … : …" and the
    // precedence quietly changes what the reader asked for.
    $isDynamic = $expression !== null && $expression !== '';
    $expr = $isDynamic ? '('.$expression.')' : null;
@endphp

{{-- Both children are always in the DOM — that is what makes a crossfade possible
     at all, since you cannot animate between an element and nothing.

     The consequence is the whole a11y story: the hidden child is still there, so
     it must be hidden from assistive tech too, or a screen reader reads BOTH
     states and the reader hears "sun moon" on every toggle. --}}
<span
    data-wk-swap
    data-effect="{{ $effect }}"
    @if($isDynamic)
        :data-active="{{ $expr }} ? 'true' : 'false'"
    @else
        data-active="{{ $active ? 'true' : 'false' }}"
    @endif
    {{ $attributes->class([$classes]) }}
>
    <span
        data-wk-swap-on
        @if($isDynamic)
            :aria-hidden="{{ $expr }} ? 'false' : 'true'"
        @else
            aria-hidden="{{ $active ? 'false' : 'true' }}"
        @endif
        class="col-start-1 row-start-1"
    >
        {{ $on ?? '' }}
    </span>
    <span
        data-wk-swap-off
        @if($isDynamic)
            :aria-hidden="{{ $expr }} ? 'true' : 'false'"
        @else
            aria-hidden="{{ $active ? 'true' : 'false' }}"
        @endif
        class="col-start-1 row-start-1"
    >
        {{ $off ?? $slot }}
    </span>
</span>