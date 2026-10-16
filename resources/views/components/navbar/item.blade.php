{{-- optimistic-ui: n/a — passthrough
     A navigation entry with an optional developer action; nothing of its own to show early. --}}
@props([
    'href' => '#',
    'active' => false,
    'scope' => null,
])

{{-- The bar's density and its `track-current`: one attribute on `navbar` sets every entry. --}}
@aware(['density' => 'default', 'trackCurrent' => false])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('navbar.item', $attributes->getAttributes());
    // Echoed into the tag or bound, the URL is written escaped once (Support\UrlProp).
    $href = \Pushery\WireKit\Support\UrlProp::text($href);

    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $active = BooleanProp::from($active, false);

    // `@aware` leaves its key in the attribute bag, where it would render as a stray attribute.
    $attributes = $attributes->except(['density', 'trackCurrent', 'track-current']);
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
        'hover:bg-[var(--color-wk-bg-hover)]',
        'focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
    ]), $scope);

    // Compact entries you are not on step back to the muted color and come forward on hover, so
    // in a dense row the current one is the only entry at full strength.
    $currentColor = 'text-[color:var(--color-wk-accent-text)] font-[number:var(--font-wk-heading-weight)]';
    $idleColor = $compact
        ? 'text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)]'
        : 'text-[color:var(--color-wk-text)]';
    $colorClasses = $active ? $currentColor : $idleColor;

    // Under the bar's `track-current` the entry carries what `x-wk-current` moves after a visit:
    // the classes only a current entry has and those only an idle one has (Support\CurrentState).
    $trackAttributes = BooleanProp::from($trackCurrent, false)
        ? \Pushery\WireKit\Support\CurrentState::attributes($currentColor, $idleColor)
        : [];

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
    {{ $attributes->except('rel')->merge($trackAttributes)->class(['wk-touch-row', $classes, $colorClasses]) }}
>
    {{ $slot }}
    @if($opensNewTab)
        <span class="sr-only">{{ __('wirekit::(opens in new tab)') }}</span>
    @endif
</a>
