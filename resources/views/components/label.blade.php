{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'required' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('label', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $required = BooleanProp::from($required, false);

    // Label classes: all values reference design tokens — no hardcoded colors or sizes
    $classes = WireKit::resolveClasses('label', 'base', implode(' ', [
        'block',
        'font-[family-name:var(--font-wk-sans)]',
        'font-[number:var(--font-wk-body-weight)]',
        'tracking-[var(--font-wk-letter-spacing)]',
        'text-[length:var(--text-wk-md)]',
        'font-medium',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);
@endphp

<label {{ $attributes->class([$classes]) }}>
    {{ $slot }}
    {{-- Required indicator uses danger-text variable (auto dark mode, no dark: needed) --}}
    @if($required)
        <span class="text-[color:var(--color-wk-danger-text)] ml-0.5" aria-hidden="true">*</span>
    @endif
</label>