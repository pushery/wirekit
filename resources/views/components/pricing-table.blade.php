{{-- optimistic-ui: n/a — client-only
     The monthly/yearly switch changes which prices are shown, all locally. --}}
@props([
    // Accessible name for the group of plans.
    'label' => config('wirekit.components.pricing-table.label') ?? __('Pricing plans'),
    // Billing intervals, as key => label, e.g.
    // :intervals="['monthly' => 'Monthly', 'annual' => 'Annual']". Given, the table
    // renders a toggle above the plans and the tiers switch their interval-keyed
    // `prices`. Omitted (the default), nothing changes and no toggle renders.
    'intervals' => null,
    // Accessible name for the interval toggle.
    'intervalLabel' => __('Billing interval'),
    // How many plans sit side by side at the widest breakpoint. Default keeps the
    // historical 1 / 2 / 3 ladder; a 2-plan table would otherwise render a gappy
    // three-column grid.
    'columns' => 3,
    // Name of the hidden input the interval is written to, so the choice reaches
    // a surrounding <form>. Only rendered when intervals are given.
    'name' => 'interval',
    // The interval as the SERVER sees it. Distinct from the first key of
    // `intervals`, which is only the opening position: this one keeps arriving,
    // so a checkout decided on the server can both read the choice and correct it.
    'interval' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Column ladder. Full literal class strings (never interpolated) so the
    // Tailwind scanner sees every one of them. One plan per row on a phone in
    // every case — plan cards do not survive being halved on a 390px screen.
    $columnClasses = match ((string) $columns) {
        '1' => 'grid-cols-1',
        '2' => 'grid-cols-1 md:grid-cols-2',
        '4' => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4',
        default => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
    };

    $classes = WireKit::resolveClasses('pricing-table', 'base', implode(' ', [
        // list-none: this is a semantic <ul> for assistive tech, not a bulleted
        // list — the UA disc markers would be pure noise beside a plan card.
        'list-none',
        'grid gap-[var(--gap-wk-md)]',
        $columnClasses,
        'items-stretch',
    ]), $scope);

    // Normalize the intervals map and pick the one selected on first paint.
    $intervalMap = is_array($intervals) && $intervals !== [] ? $intervals : null;
    $defaultInterval = $intervalMap !== null ? (string) array_key_first($intervalMap) : null;

    // What the table is priced at right now. `interval` is the server's answer and
    // wins; the first key is only the opening position. Getting the precedence
    // backwards would make the prop inert exactly where it is used most.
    $serverInterval = $interval !== null ? (string) $interval : $defaultInterval;

    // Both toggle branches resolve through resolveClasses and are interpolated
    // into the Alpine ternary, rather than sitting in it as literals: a runtime
    // :class binding is out of reach of WireKit::scope(), so appearance written
    // there cannot be personalized. Same pattern as segmented-control; both class
    // strings are listed in resources/views/_safelist.blade.php so the Tailwind
    // scanner still generates them.
    $intervalSelectedClasses = WireKit::resolveClasses('pricing-table', 'interval-selected', implode(' ', [
        'bg-[var(--color-wk-bg-elevated)]',
        'text-[color:var(--color-wk-text)]',
        'shadow-[var(--shadow-wk-sm)]',
    ]), $scope);

    $intervalUnselectedClasses = WireKit::resolveClasses('pricing-table', 'interval-unselected', implode(' ', [
        'text-[color:var(--color-wk-text-muted)]',
    ]), $scope);
@endphp

{{-- A list, not a pile of divs: the tiers are a set the reader compares, and a
     screen reader should hear "3 items" before wading in. --}}
{{-- The inline list-style is not redundant with the list-none class: the docs
     sandbox iframe renders previews WITHOUT the developer's Tailwind build, so
     `list-none` is a dead class name there and the plans grow UA bullets.

     It DOES load dist/wirekit.css — that is why every token in this component
     resolves in a preview. (An earlier version of this comment claimed the
     opposite; dist/wirekit.css carries a rule written specifically to fix a
     sandbox-iframe symptom, which could not work if the sheet never loaded.) --}}
@if($intervalMap !== null)
{{-- The toggle owns `interval` for the whole table. It sits OUTSIDE the <ul>
     because a list may only contain list items, and the tiers read the value
     through the Alpine scope rather than a prop — Blade cannot pass anything
     into slot content that was already rendered in the caller's scope. --}}
{{-- `wire:model` is taken off the <ul> and put on the hidden input below. A
     directive on the list would bind the wrong element — a <ul> has no value —
     and Livewire would quietly observe nothing. This is the same passthrough
     nine other components already do; see segmented-control. --}}
<div
    x-data="wirekitPricingTable({ interval: {{ \Pushery\WireKit\Support\AlpinePayload::from($serverInterval) }} })"
    @if($interval !== null) data-wk-server-value="{{ $serverInterval }}" @endif
    data-wk-pricing-intervals
>
    {{-- Inside the wrapper and BEFORE the <ul>, because a list may only hold list
         items — the same reason the toggle sits here. --}}
    <input
        type="hidden"
        x-ref="hiddenInput"
        name="{{ $name }}"
        value="{{ $serverInterval }}"
        {{ $attributes->whereStartsWith('wire:model') }}
    >
    <div
        role="group"
        aria-label="{{ $intervalLabel }}"
        class="mb-[var(--space-wk-md)] inline-flex items-center gap-[var(--space-wk-xs)] rounded-[var(--radius-wk-full)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] bg-[var(--color-wk-bg-subtle)] p-[var(--space-wk-xs)]"
    >
        @foreach($intervalMap as $intervalKey => $intervalLabelText)
            {{-- aria-pressed, not just a tint: "this interval is selected" is a
                 state a reader who cannot see the fill still needs. --}}
            <button
                type="button"
                x-on:click="interval = {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $intervalKey) }}"
                :aria-pressed="interval === {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $intervalKey) }} ? 'true' : 'false'"
                :class="interval === {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $intervalKey) }}
                    ? '{{ $intervalSelectedClasses }}'
                    : '{{ $intervalUnselectedClasses }}'"
                class="cursor-pointer rounded-[var(--radius-wk-full)] px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-sm)] text-[length:var(--text-wk-sm)] transition-colors duration-[var(--transition-wk-duration)] focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                data-wk-pricing-interval-toggle="{{ $intervalKey }}"
            >{{ $intervalLabelText }}</button>
        @endforeach
    </div>

    <ul
        role="list"
        aria-label="{{ $label }}"
        data-wk-pricing-table
        style="list-style: none; margin: 0; padding: 0;"
        {{ $attributes->whereDoesntStartWith('wire:model')->class([$classes]) }}
    >
        {{ $slot }}
    </ul>
</div>
@else
<ul
    role="list"
    aria-label="{{ $label }}"
    data-wk-pricing-table
    style="list-style: none; margin: 0; padding: 0;"
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</ul>
@endif
