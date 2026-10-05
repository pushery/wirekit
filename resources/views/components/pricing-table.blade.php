{{-- optimistic-ui: n/a — client-only
     The monthly/yearly switch changes which prices are shown, all locally. --}}
@props([
    // Accessible name for the group of plans.
    'label' => config('wirekit.components.pricing-table.label') ?? __('wirekit::Pricing plans'),
    // Billing intervals, as key => label, e.g.
    // :intervals="['monthly' => 'Monthly', 'annual' => 'Annual']". Given, the table
    // renders a toggle above the plans and the tiers switch their interval-keyed
    // `prices`. Omitted (the default), nothing changes and no toggle renders.
    'intervals' => null,
    // Accessible name for the interval toggle.
    'intervalLabel' => __('wirekit::Billing interval'),
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

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('pricing-table', $attributes->getAttributes());

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

    // Normalize the intervals map and pick the one selected on first paint. It may arrive as a
    // Collection, which is what `pluck('label', 'key')` gives.
    $intervals = \Pushery\WireKit\Support\ListProp::from($intervals);
    $intervalMap = is_array($intervals) && $intervals !== [] ? $intervals : null;
    $defaultInterval = $intervalMap !== null ? (string) array_key_first($intervalMap) : null;

    // What the table is priced at right now. `interval` is the server's answer and
    // wins; the first key is only the opening position. Getting the precedence
    // backwards would make the prop inert exactly where it is used most.
    $serverInterval = $interval !== null ? (string) $interval : $defaultInterval;

    // Both toggle branches resolve through resolveClasses and are interpolated
    // into the Alpine ternary, rather than sitting in it as literals: a runtime
    // :class binding is out of reach of WireKit::scope(), so appearance written
    // there cannot be personalized. Same pattern as segmented-control, and the
    // class strings stay literals here, where the Tailwind scanner finds them.
    $intervalSelectedClasses = WireKit::resolveClasses('pricing-table', 'interval-selected', implode(' ', [
        'bg-[var(--color-wk-bg-elevated)]',
        'text-[color:var(--color-wk-text)]',
        'shadow-[var(--shadow-wk-sm)]',
    ]), $scope);

    $intervalUnselectedClasses = WireKit::resolveClasses('pricing-table', 'interval-unselected', implode(' ', [
        'text-[color:var(--color-wk-text-muted)]',
    ]), $scope);
    // A caller's `x-ref` belongs to the caller's component. With intervals the list sits inside
    // the toggle's root, which would keep it, so the name moves to `x-wk-ref` on the list and
    // the outermost element marks the boundary (resources/js/utils/caller-ref.js).
    $callerRef = trim((string) $attributes->get('x-ref', ''));
    // A caller's `form` goes to the field this component submits, and only there: on the
    // wrapper it is not a valid attribute and joins nothing to the form (Support\FormOwner).
    $formOwner = \Pushery\WireKit\Support\FormOwner::of($attributes);
    $attributes = $attributes->except('form');
    $attributes = $attributes->except('x-ref')->merge(array_filter([
        'x-wk-ref' => $callerRef !== '' ? $callerRef : null,
        'data-wk-ref-scope' => $callerRef !== '' && $intervalMap === null ? true : null,
    ]));

    // A caller's `wire:key`, `x-show`, `wire:show` and their transitions are about the whole
    // table, so they go on the outermost element (see Support\OuterAttributes). With intervals
    // the list sits inside the toggle's wrapper, so they move there; without intervals the list
    // is outermost and keeps them in its bag.
    [$outerAttributes, $withoutOuterAttributes] = \Pushery\WireKit\Support\OuterAttributes::split($attributes);

    if ($intervalMap !== null) {
        $attributes = $withoutOuterAttributes;
    } else {
        $outerAttributes = new \Illuminate\View\ComponentAttributeBag([]);
    }
@endphp

{{-- A list, not a pile of divs: the tiers are a set the reader compares, and a
     screen reader should hear "3 items" before wading in. --}}
{{-- The inline list-style repeats the list-none class on purpose: `list-none` exists only
     where a Tailwind build scanned this view, and the inline rule keeps the plans free of
     UA bullets in a page whose stylesheet did not. The tokens resolve from
     dist/wirekit.css either way. --}}
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
        {{-- The observed value is NOT interpolated into the seed, and that is
             deliberate: a Livewire morph rewrites `x-data`, Alpine re-initializes
             on the change, and an effect queued against the pre-morph scope then
             writes the pre-morph value last. Keeping this attribute
             byte-identical across renders leaves the scope alone, so
             `data-wk-server-value` + observeServerValue is the one update path.
             The factory reads that same attribute at init. --}}
    {{-- `default` is a PROP, so it renders identically on every morph and the
         attribute stays byte-stable. Only the observed value had to leave. --}}
    x-data="wirekitPricingTable({ default: {{ \Pushery\WireKit\Support\AlpinePayload::from($defaultInterval) }} })"
    @if($interval !== null) data-wk-server-value="{{ $serverInterval }}" @endif
    @if($callerRef !== '') data-wk-ref-scope @endif
    data-wk-pricing-intervals
    {{ $outerAttributes }}
>
    {{-- Inside the wrapper and BEFORE the <ul>, because a list may only hold list
         items — the same reason the toggle sits here. --}}
    <input
        type="hidden"
        x-ref="hiddenInput"
        name="{{ $name }}"
        value="{{ $serverInterval }}"
        {{ $attributes->whereStartsWith('wire:model') }}
        @if($formOwner) form="{{ $formOwner }}" @endif
    >
    <div
        role="group"
        aria-label="{{ $intervalLabel }}"
        class="mb-[var(--space-wk-md)] inline-flex items-center gap-[var(--space-wk-xs)] rounded-[var(--radius-wk-full)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] bg-[var(--color-wk-bg-subtle)] p-[var(--space-wk-xs)]"
    >
        @foreach($intervalMap as $intervalKey => $intervalLabelText)
            {{-- `wk-state-button` is the marker the stylesheet's forced-colors rule frames the
                 selected interval by: its raised surface and shadow are what that mode removes.
                 aria-pressed, not just a tint: "this interval is selected" is a
                 state a reader who cannot see the fill still needs. --}}
            <button
                type="button"
                x-on:click="interval = {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $intervalKey) }}"
                {{-- A STATIC value as well as the bound one. Until Alpine evaluates the
                     binding the button carries no `aria-pressed` at all, so the
                     server-rendered document — which is what a screen reader meets first, and
                     the only document at all under a strict CSP that blocks the bundle — says
                     nothing about which interval is selected. The server knows: it is
                     `$serverInterval`, the same value the factory is seeded with. --}}
                aria-pressed="{{ (string) $intervalKey === $serverInterval ? 'true' : 'false' }}"
                :aria-pressed="interval === {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $intervalKey) }} ? 'true' : 'false'"
                :class="interval === {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $intervalKey) }}
                    ? {{ \Pushery\WireKit\Support\AlpinePayload::string($intervalSelectedClasses) }}
                    : {{ \Pushery\WireKit\Support\AlpinePayload::string($intervalUnselectedClasses) }}"
                class="wk-state-button cursor-pointer rounded-[var(--radius-wk-full)] px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-sm)] text-[length:var(--text-wk-sm)] transition-colors duration-[var(--transition-wk-duration)] focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                data-wk-pricing-interval-toggle="{{ $intervalKey }}"
            >{{ $intervalLabelText }}</button>
        @endforeach
    </div>

    <ul data-wk-prose-skip
        @unless($attributes->has('role')) role="list" @endunless
        @unless($attributes->has('aria-label') || $attributes->has('aria-labelledby')) aria-label="{{ $label }}" @endunless
        data-wk-pricing-table
        {{ $attributes->merge(['style' => 'list-style: none; margin: 0; padding: 0;'])->whereDoesntStartWith('wire:model')->class([$classes]) }}
    >
        {{ $slot }}
    </ul>
</div>
@else
<ul data-wk-prose-skip
    @unless($attributes->has('role')) role="list" @endunless
    @unless($attributes->has('aria-label') || $attributes->has('aria-labelledby')) aria-label="{{ $label }}" @endunless
    data-wk-pricing-table
    {{ $attributes->merge(['style' => 'list-style: none; margin: 0; padding: 0;'])->class([$classes]) }}
>
    {{ $slot }}
</ul>
@endif
