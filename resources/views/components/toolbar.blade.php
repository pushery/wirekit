@props([
    'sticky' => false,
    'density' => 'comfortable',
    'align' => 'between',
    'ariaLabel' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

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

<div
    role="toolbar"
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
