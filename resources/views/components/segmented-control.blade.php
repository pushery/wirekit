{{-- optimistic-ui: supported
     Pass `optimistic="method"` and the segment moves the moment it is clicked.
     Discrete choice, one value, and the previous one is the server's — so an
     undo destroys nothing the user typed. The optimistic scope nests INSIDE
     this component and binds to `selected`. --}}
@props([
    'label' => null,
    // Livewire method to call optimistically. The segment moves immediately and
    // is put back if the call fails. Absent -> this component renders exactly as
    // it did before, down to the byte.
    'optimistic' => null,
    'options' => [],
    'value' => null,
    'size' => config('wirekit.components.segmented-control.size', 'md'),
    // Disable the whole control: aria-disabled on the radiogroup + each segment,
    // the buttons become non-interactive, and the group dims via
    // --opacity-wk-disabled.
    'disabled' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $disabled = BooleanProp::from($disabled, false);

    $id = \Pushery\WireKit\Support\DomId::unique($attributes->get('id') ?? $attributes->get('name'), 'segmented-'); // page-unique DOM id; see Support\DomId
    $name = $attributes->get('name', $id);

    // Container wrapping the pill-style segments
    $containerClasses = WireKit::resolveClasses('segmented-control', 'base', implode(' ', [
        'inline-flex',
        'rounded-[var(--radius-wk-md)]',
        'bg-[var(--color-wk-bg-muted)]',
        'p-0.5',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    // Selected / unselected segment appearance.
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

@php
    // The optimistic layer NESTS INSIDE this component, and the direction is not
    // interchangeable: a nested Alpine component's method reads and writes its
    // parent's properties through `this`, never the other way around. So it has
    // to be the child to reach `selected`, and the segments have to be inside it
    // to reach its `run()`.
    //
    // `after: '_notify'` keeps a plain HTML form honest — the hidden input is
    // synced there, and without the call a rollback would leave the form
    // submitting the segment that was just taken back.
    $optimisticConfig = ($optimistic === null || $disabled) ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        'bind' => 'selected',
        'after' => '_notify',
        'action' => $optimistic,
        'debug' => (bool) config('app.debug'),
        // A second pick while one is in flight would resolve by whichever answer
        // arrives last — network timing, which is both wrong and untestable.
        'mode' => 'reject',
        'messages' => [
            'pending' => __('Saving'),
            'reverted' => __('Could not save. Change undone.'),
        ],
    ]);
@endphp

<div class="space-y-1.5">
    @if($label)
        <x-wirekit::label>{{ $label }}</x-wirekit::label>
    @endif

    <div
        {{ $attributes->whereDoesntStartWith('wire:model')->class([$containerClasses, $disabled ? 'opacity-[var(--opacity-wk-disabled)]' : '']) }}
        {{-- Selection, the hidden-input mirror and the whole keyboard model live
             in the factory (resources/js/components/segmented-control.js). The
             handlers cannot be inline: Alpine's CSP build parses neither the
             multi-statement click nor the optional chaining the arrows used, so
             under a strict Content-Security-Policy the segments went dead.

             The factory's init() writes the initial value into the hidden input,
             which is what the old x-init here did. --}}
        x-data="wirekitSegmentedControl({ selected: {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $selected) }} })"
        {{-- Without the optimistic layer the radiogroup IS this element, exactly
             as before. With it, the role moves one level in — because the layer
             carries a live region, and a live region is not a radio. A
             radiogroup may only own radios, so the announcer cannot sit in it. --}}
        @unless($optimisticConfig)
            role="radiogroup"
            @if($disabled) aria-disabled="true" @endif
            @if($label) aria-label="{{ $label }}" @endif
        @endunless
    >
        {{-- Hidden input inside x-data scope so $refs.hiddenInput resolves correctly.
             Must be within the same Alpine component for x-ref to work. --}}
        <input type="hidden" id="{{ $id }}" name="{{ $name }}" {{ $attributes->whereStartsWith('wire:model') }} x-ref="hiddenInput" />

        @if($optimisticConfig)
            {{-- `display: contents` on both wrappers: the segments must keep
                 participating in the flex row on the element that carries the
                 padding and the muted background, and two extra boxes between
                 them and it would break that layout without changing a class. --}}
            <div x-data="wirekitOptimistic({{ $optimisticConfig }})" style="display: contents">
                <div
                    role="radiogroup"
                    style="display: contents"
                    @if($disabled) aria-disabled="true" @endif
                    @if($label) aria-label="{{ $label }}" @endif
                >
        @endif

        @foreach($options as $optValue => $optLabel)
            {{-- Static aria-checked + tabindex mirror the initial state so
                 axe-core's pre-Alpine-init scan sees a complete radiogroup;
                 Alpine overrides reactively once it boots. --}}
            <button
                type="button"
                role="radio"
                @disabled($disabled)
                aria-disabled="{{ $disabled ? 'true' : 'false' }}"
                aria-checked="{{ $selected === $optValue ? 'true' : 'false' }}"
                {{-- Js::from, not a hand-quoted '{{ … }}': an option value
                     carrying an apostrophe ("Rock 'n' Roll") would otherwise
                     close the JS string mid-expression and break every binding
                     on this button. Same defect class already fixed on calendar. --}}
                :aria-checked="selected === {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $optValue) }} ? 'true' : 'false'"
                tabindex="{{ $disabled ? '-1' : ($selected === $optValue ? '0' : '-1') }}"
                @if(! $disabled)
                    :tabindex="selected === {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $optValue) }} ? '0' : '-1'"
                    {{-- run(), not select(): the layer snapshots, writes `selected`
                         through its bind, syncs the hidden input via `after`, and
                         puts everything back if the call fails. Arrow-key
                         navigation routes here too — _focusAt() moves selection
                         with a `.click()`, so there is one path, not two. --}}
                    {{-- aria-busy on the segment, not on the group: the group is
                         not what the user is waiting on, and a busy container
                         tells a screen reader its whole subtree is unreliable. --}}
                    @if($optimisticConfig) x-bind:aria-busy="isPending" @endif
                    @click="{{ $optimisticConfig ? 'run' : 'select' }}({{ \Pushery\WireKit\Support\AlpinePayload::from((string) $optValue) }})"
                    {{-- The full documented model: both axes move selection with
                         focus and wrap at the ends, Home/End jump. All four keys
                         were promised on the docs page; only the two horizontal
                         ones were ever bound, and neither wrapped. --}}
                    @keydown.arrow-right.prevent="focusNext($el)"
                    @keydown.arrow-down.prevent="focusNext($el)"
                    @keydown.arrow-left.prevent="focusPrevious($el)"
                    @keydown.arrow-up.prevent="focusPrevious($el)"
                    @keydown.home.prevent="focusFirst()"
                    @keydown.end.prevent="focusLast()"
                @endif
                class="{{ $segmentClasses }} {{ $sizeClasses }}"
                :class="selected === {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $optValue) }}
                    ? '{{ $segmentSelectedClasses }}'
                    : '{{ $segmentUnselectedClasses }}'"
            >
                {{ $optLabel }}
            </button>
        @endforeach

        @if($optimisticConfig)
                </div>
                {{-- Outside the radiogroup, inside the optimistic scope. Rendered
                     unconditionally and starting empty: a region that arrives
                     together with its text is a new node, and nothing is
                     announced at all. --}}
                <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
            </div>
        @endif
    </div>
</div>
