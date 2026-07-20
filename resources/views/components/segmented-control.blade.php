@props([
    'label' => null,
    'options' => [],
    'value' => null,
    'size' => config('wirekit.components.segmented-control.size', 'md'),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $id = $attributes->get('id', $attributes->get('name', 'segmented-' . \Illuminate\Support\Str::random(6)));
    $name = $attributes->get('name', $id);

    // Container wrapping the pill-style segments
    $containerClasses = WireKit::resolveClasses('segmented-control', 'base', implode(' ', [
        'inline-flex',
        'rounded-[var(--radius-wk-md)]',
        'bg-[var(--color-wk-bg-muted)]',
        'p-0.5',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    // Selected / unselected segment appearance (WIRE-178).
    //
    // These used to be string literals inside the Alpine `:class` ternary below.
    // That put them out of reach of WireKit::scope(): resolveClasses runs at
    // RENDER time in PHP, while `:class` is a RUNTIME reactive binding, so any
    // state-dependent styling written there silently opts out of personalization.
    // A brand that themes its active segment (gold on navy, say) could not adopt
    // this component without losing that styling — the accent token does not help,
    // because the selected pill is an elevated surface rather than an accent fill.
    //
    // Resolving both branches here and interpolating the results keeps the runtime
    // behavior identical while making both reachable.
    //
    // NOTE: these class strings now only exist inside PHP, so Tailwind's content
    // scanner no longer sees them where it used to. They are listed literally in
    // resources/views/_safelist.blade.php — without that entry they get purged
    // and the segments render unstyled.
    $segmentSelectedClasses = WireKit::resolveClasses('segmented-control', 'segment-selected', implode(' ', [
        'bg-[var(--color-wk-bg-elevated)]',
        'text-[color:var(--color-wk-text)]',
        'shadow-[var(--shadow-wk-sm)]',
        'font-[number:var(--font-wk-heading-weight)]',
    ]), $scope);

    $segmentUnselectedClasses = WireKit::resolveClasses('segmented-control', 'segment-unselected', implode(' ', [
        'text-[color:var(--color-wk-text-muted)]',
        'hover:text-[color:var(--color-wk-text)]',
    ]), $scope);

    // Individual segment button classes
    $segmentClasses = implode(' ', [
        'relative',
        'cursor-pointer',
        'rounded-[var(--radius-wk-sm)]',
        'transition-all duration-[var(--transition-wk-duration)]',
        'focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
    ]);

    $sizeClasses = match ($size) {
        'sm' => 'px-[var(--padding-wk-x-sm)] py-1 text-[length:var(--text-wk-sm)]',
        'lg' => 'px-[var(--padding-wk-x-lg)] py-2 text-[length:var(--text-wk-lg)]',
        default => 'px-[var(--padding-wk-x-md)] py-1.5 text-[length:var(--text-wk-md)]',
    };

    // Determine the default selected value
    $selected = $value ?? array_key_first($options);
@endphp

<div class="space-y-1.5">
    @if($label)
        <x-wirekit::label>{{ $label }}</x-wirekit::label>
    @endif

    <div
        {{ $attributes->whereDoesntStartWith('wire:model')->class([$containerClasses]) }}
        x-data="{ selected: '{{ $selected }}' }"
        x-init="$refs.hiddenInput.value = selected"
        role="radiogroup"
        @if($label) aria-label="{{ $label }}" @endif
    >
        {{-- Hidden input inside x-data scope so $refs.hiddenInput resolves correctly.
             Must be within the same Alpine component for x-ref to work. --}}
        <input type="hidden" id="{{ $id }}" name="{{ $name }}" {{ $attributes->whereStartsWith('wire:model') }} x-ref="hiddenInput" />

        @foreach($options as $optValue => $optLabel)
            {{-- Static aria-checked + tabindex mirror the initial state so
                 axe-core's pre-Alpine-init scan sees a complete radiogroup;
                 Alpine overrides reactively once it boots. --}}
            <button
                type="button"
                role="radio"
                aria-checked="{{ $selected === $optValue ? 'true' : 'false' }}"
                :aria-checked="selected === '{{ $optValue }}' ? 'true' : 'false'"
                tabindex="{{ $selected === $optValue ? '0' : '-1' }}"
                :tabindex="selected === '{{ $optValue }}' ? '0' : '-1'"
                @click="selected = '{{ $optValue }}'; $refs.hiddenInput.value = selected; $refs.hiddenInput.dispatchEvent(new Event('input', { bubbles: true }))"
                @keydown.arrow-right.prevent="$el.nextElementSibling?.focus(); $el.nextElementSibling?.click()"
                @keydown.arrow-left.prevent="$el.previousElementSibling?.focus(); $el.previousElementSibling?.click()"
                class="{{ $segmentClasses }} {{ $sizeClasses }}"
                :class="selected === '{{ $optValue }}'
                    ? '{{ $segmentSelectedClasses }}'
                    : '{{ $segmentUnselectedClasses }}'"
            >
                {{ $optLabel }}
            </button>
        @endforeach
    </div>
</div>
