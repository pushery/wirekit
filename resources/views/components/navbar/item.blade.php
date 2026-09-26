{{-- optimistic-ui: n/a — passthrough
     A navigation entry with an optional developer action; nothing of its own to show early. --}}
@props([
    'href' => '#',
    'active' => false,
    'scope' => null,
])

{{-- The bar's density: one attribute on `navbar` sets every entry. --}}
@aware(['density' => 'default'])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('navbar.item', $attributes->getAttributes());

    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $active = BooleanProp::from($active, false);

    // `@aware` leaves its key in the attribute bag, where it would render as a stray attribute.
    $attributes = $attributes->except(['density']);
    $compact = $density === 'compact';

    // Navbar item — navigation link with active state indicator.
    $classes = WireKit::resolveClasses('navbar.item', 'base', implode(' ', [
        'inline-flex items-center',
        $compact ? 'px-[var(--padding-wk-x-xs)]' : 'px-[var(--padding-wk-x-sm)]',
        $compact ? 'py-[var(--padding-wk-y-xs)]' : 'py-[var(--padding-wk-y-sm)]',
        $compact ? 'text-[length:var(--text-wk-sm)]' : 'text-[length:var(--text-wk-md)]',
        'rounded-[var(--radius-wk-sm)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'hover:bg-[var(--color-wk-bg-subtle)]',
        'focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
    ]), $scope);

    // Compact entries you are not on step back to the muted color and come forward on hover, so
    // in a dense row the current one is the only entry at full strength.
    $colorClasses = match (true) {
        $active => 'text-[color:var(--color-wk-accent-text)] font-[number:var(--font-wk-heading-weight)]',
        $compact => 'text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)]',
        default => 'text-[color:var(--color-wk-text)]',
    };

    // Auto-inject rel="noopener noreferrer" + SR hint when target="_blank".
    // See sidebar/item.blade.php for the rationale on except('rel') + explicit
    // rel attribute — $attributes->merge treats rel as a default, so a caller
    // that passes rel="prev" would silently defeat our security injection.
    $targetAttr = $attributes->get('target', '');
    $opensNewTab = str_contains($targetAttr, '_blank');
    $relAttr = $attributes->get('rel', '');
    $finalRel = $opensNewTab && ! str_contains($relAttr, 'noopener')
        ? trim($relAttr.' noopener noreferrer')
        : $relAttr;
    $computedRel = $opensNewTab ? $finalRel : ($relAttr ?: null);
@endphp

<a data-wk-prose-skip
    href="{{ $href }}"
    @if($active) aria-current="page" @endif
    @if($computedRel) rel="{{ $computedRel }}" @endif
    {{ $attributes->except('rel')->class([$classes, $colorClasses]) }}
>
    {{ $slot }}
    @if($opensNewTab)
        <span class="sr-only">{{ __('wirekit::(opens in new tab)') }}</span>
    @endif
</a>
