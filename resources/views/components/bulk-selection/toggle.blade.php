{{-- optimistic-ui: n/a — client-only
     It selects or clears the page's rows by clicking their boxes, which carry the application's
     binding; its own state is read from them. --}}
@props([
    // The checkbox's accessible name. "Select all rows on this page" in the reader's language
    // without one.
    'label' => null,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('bulk-selection.toggle', $attributes->getAttributes());

    // The label is the checkbox's name, an attribute, so markup is read as its text.
    $label = filled($label) ? ($label instanceof \Illuminate\Contracts\Support\Htmlable ? \Pushery\WireKit\Support\SlotContent::textOf($label) : \Pushery\WireKit\Support\NumericProp::text($label)) : __('wirekit::Select all rows on this page');

    // The bag lands on the checkbox, where `disabled="false"` would read as disabled: HTML reads a
    // flag's presence, not its value.
    $attributes = \Pushery\WireKit\Support\BooleanProp::stripFalseHtmlFlags($attributes);

    // A caller's `x-on:change` runs beside the view's own, which selects the page, as `@change`.
    $attributes = \Pushery\WireKit\Support\CallerListeners::beside($attributes, ['x-on:change']);

    // Disabled while the page has no row to choose, unless the caller decides it: a `disabled`
    // of the caller's, written or bound in Alpine, stays the only one.
    $bindsDisabled = ! \Pushery\WireKit\Support\CallerAttribute::writes($attributes, 'disabled')
        && ! $attributes->has('x-bind:disabled')
        && ! $attributes->has(':disabled');

    // The size and the ring of the row boxes in a table, as the data table draws its own.
    $classes = \Pushery\WireKit\WireKit::resolveClasses('bulk-selection.toggle', 'base', implode(' ', [
        'h-4 w-4',
        'rounded-[var(--radius-wk-sm)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'accent-[var(--color-wk-accent)]',
        'cursor-pointer',
        'focus-visible:outline-hidden',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
    ]), $scope);
@endphp

{{-- Checked when every row of the page is, mixed when some are: `indeterminate` is a property,
     so it is set from the block's state rather than rendered. Disabled while the page has no row
     to choose. Outside the Shift range, which covers the rows. --}}
<input
    type="checkbox"
    data-wk-bulk-toggle
    data-wk-range-skip
    x-bind:checked="pageAll"
    x-effect="$el.indeterminate = pageSome"
    @if($bindsDisabled) x-bind:disabled="pageEmpty" @endif
    x-on:change="toggle()"
    @unless(\Pushery\WireKit\Support\CallerAttribute::writes($attributes, 'aria-label') || \Pushery\WireKit\Support\CallerAttribute::writes($attributes, 'aria-labelledby')) aria-label="{{ $label }}" @endunless
    {{ $attributes->except(['type', 'x-bind:checked'])->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}
/>
