@props([
    'label' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // A group clusters related items under an optional label. The label acts
    // as a section heading (via aria-label on a role="group" container) so
    // screen readers announce "group, <label>" when the user enters.
    $groupClasses = WireKit::resolveClasses('sidebar.group', 'base', 'flex flex-col gap-[2px]', $scope);

    // Label styling — small uppercase label, muted color.
    $labelClasses = WireKit::resolveClasses('sidebar.group', 'label', implode(' ', [
        'px-[var(--padding-wk-x-sm)] pt-[var(--padding-wk-y-sm)] pb-[2px]',
        'text-[length:var(--text-wk-xs)]',
        'font-[number:var(--font-wk-heading-weight)]',
        'uppercase tracking-wider',
        'text-[var(--color-wk-text-subtle)]',
    ]), $scope);
@endphp

<div role="group" @if($label) aria-label="{{ $label }}" @endif {{ $attributes->class([$groupClasses]) }}>
    @if($label)
        {{-- Visible label; also the accessible name via aria-label above.
             We render it visually because sighted users benefit from the grouping too. --}}
        <div class="{{ $labelClasses }}">{{ $label }}</div>
    @endif
    {{ $slot }}
</div>
