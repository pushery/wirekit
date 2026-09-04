{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'sticky' => false,
    'density' => 'comfortable',
    'align' => 'between',
    'ariaLabel' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('toolbar', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $sticky = BooleanProp::from($sticky, false);

    $densityValue = match ($density) {
        'comfortable', 'compact' => $density,
        default => WireKit::validateProp('toolbar', 'density', $density, ['comfortable', 'compact']),
    };

    $alignValue = match ($align) {
        'between', 'start', 'end' => $align,
        default => WireKit::validateProp('toolbar', 'align', $align, ['between', 'start', 'end']),
    };

    $justifyClass = match ($alignValue) {
        'between' => 'justify-between',
        'start' => 'justify-start',
        'end' => 'justify-end',
    };

    /*
     * Equal horizontal + vertical inset so the leading button doesn't
     * sit flush against the container edge. Without `px-*` the first
     * action (typically a "Save" / "Filter" button) ended up touching
     * the toolbar's left border — visually broken on every sticky-
     * pinned variant where the left border is a strong scroll-track
     * edge. Sized off the same density token as `py-*` so the
     * button-cluster sits in a uniform inset frame.
     */
    $paddingClass = match ($densityValue) {
        'compact' => 'px-[var(--space-wk-xs,0.25rem)] py-[var(--space-wk-xs,0.25rem)]',
        default => 'px-[var(--space-wk-sm,0.5rem)] py-[var(--space-wk-sm,0.5rem)]',
    };

    $gapClass = match ($densityValue) {
        'compact' => 'gap-[var(--space-wk-sm,0.5rem)]',
        default => 'gap-[var(--space-wk-md,1rem)]',
    };

    $stickyClasses = $sticky
        ? 'sticky top-0 z-[var(--z-wk-sticky,10)] bg-[var(--color-wk-bg)]'
        : '';

    $baseClasses = WireKit::resolveClasses('toolbar', 'base', implode(' ', array_filter([
        'flex flex-wrap items-center',
        $justifyClass,
        $gapClass,
        $paddingClass,
        $stickyClasses,
        'font-[family-name:var(--font-wk-sans)]',
    ])), $scope);
@endphp

{{-- `role="group"`, NOT `role="toolbar"` — the same correction action-bar took, for
     the same reason and after the same mistake.

     The toolbar role is a composite-widget promise: Tab reaches the whole bar ONCE,
     Left/Right move between the controls inside it, Home/End jump to the ends. This
     component binds no keys at all. It is a layout wrapper whose children are
     whatever the caller passed, so Tab walks every one of them and the arrows do
     nothing — a screen reader announced "toolbar" and told its user to press keys
     that were never bound. Nothing reported it, and nothing could: axe has no rule
     for a composite role shipped without its keyboard model, so every lane stayed
     green over it.

     Adding the model here was the other way out and it is the wrong one. A roving
     tabindex has to own the tab sequence of every control in the bar, and this bar's
     contents are arbitrary: the leading slot is documented for a SEARCH FIELD, where
     Left/Right belong to the text and taking them is a regression on its own, and
     several WireKit children render a tab stop of their own (a tooltip trigger
     defaults to one) that a roving index neither sees nor moves. A half-owned roving
     model is worse than none — it leaves the extra tab stops in place AND makes the
     arrows look bound.

     `role="group"` says the true thing instead — a related set of controls, each
     reached with Tab — and costs the caller nothing. Where a real roving model is
     wanted, the catalog has one: `<x-wirekit::editor.toolbar>` owns its own children
     and can therefore keep the promise. --}}
<div
    role="group"
    @if($ariaLabel) aria-label="{{ $ariaLabel }}" @endif
    {{ $attributes->class([$baseClasses]) }}
>
    {{-- Leading slot (search, primary controls).
         `flex-1 basis-0 min-w-[min(100%,14rem)]` is the responsive contract:
         the search cluster grows to fill spare space but NEVER shrinks below
         14rem (or the full container width on a phone narrower than that).
         The old `min-w-0` let it collapse to zero — on a narrow viewport the
         search field vanished instead of forcing the toolbar to wrap to a
         second row. With a real min-width, flex-wrap pushes the filters /
         actions to the next line once they no longer fit beside a usable
         search field. --}}
    @if(isset($leading))
        <div class="flex items-center gap-[var(--space-wk-sm,0.5rem)] flex-1 basis-0 min-w-[min(100%,14rem)]">
            {{ $leading }}
        </div>
    @endif

    {{-- Filters slot (badges, selects, chips) --}}
    @if(isset($filters))
        <div class="flex flex-wrap items-center gap-[var(--space-wk-sm,0.5rem)]">
            {{ $filters }}
        </div>
    @endif

    {{-- Default/trailing slot (action buttons) --}}
    @if(isset($trailing))
        <div class="flex items-center gap-[var(--space-wk-sm,0.5rem)]">
            {{ $trailing }}
        </div>
    @elseif(!$slot->isEmpty())
        {{-- Default-slot path: content passed WITHOUT the named leading/
             filters/trailing slots. This wrapper must mirror the toolbar's
             own responsive flex behavior (`flex-wrap justify-between
             w-full`) so default-slot content still wraps to a second row on
             a narrow viewport instead of cramming on one line — the
             named-slot path already wraps via the root. Without `flex-wrap`
             here, a search field + filter selects + an action button dumped
             into the default slot were squeezed onto one line and the
             leading field collapsed to nothing on mobile. --}}
        <div class="flex flex-wrap items-center justify-between gap-[var(--space-wk-sm,0.5rem)] w-full">
            {{ $slot }}
        </div>
    @endif
</div>
