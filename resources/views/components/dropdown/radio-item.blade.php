{{-- optimistic-ui: candidate
     Same, with the group-rollback wrinkle: the snapshot is the previously selected sibling.

     ⚠️ TWO OBSTACLES WERE NAMED HERE. ONE IS SETTLED, AND THE OTHER ONE WAS NOT
     THE RIGHT NAME FOR WHAT IS ACTUALLY IN THE WAY.

     SETTLED — the announcer wrapper. This paragraph said the `display: contents`
     wrapper landing between `role="menu"` and its owned item still had to be
     resolved. It was, on checkbox-item, and by measurement rather than by the
     spec's promise: `ariaSnapshot()` in chromium, firefox and webkit shows both
     items staying DIRECT children of the menu with the wrapper in place. That
     answer transfers here unchanged — same wrapper, same menu, same role family.

     WRONGLY NAMED — "the value does not live on this component, and WireKit has
     no group-level surface to bind to". That is true of `radio`, where the
     browser deselects the sibling before any handler runs, and it was inherited
     from there. It is NOT true here: this item's group value is a single Alpine
     property named by `model` on an ancestor scope, and `wirekitOptimistic`
     already binds to exactly that shape — `bind: '<model>'`, no `value` of its
     own, nested inside the scope that owns it. segmented-control ships on that
     mechanism today. The snapshot the rollback needs is the ancestor's previous
     value, which is one string, and putting it back moves every sibling's
     display with it because they all read the same property.

     WHAT IS ACTUALLY IN THE WAY — arbitration, and it is a consequence of the
     component being one ITEM of a group rather than the group. Each item would
     mount its own layer over the shared property, and `mode: 'reject'` guards a
     layer against itself, not against its siblings. Pick B, then pick C while B
     is still in flight: C snapshots B's optimistic value, and a refusal of B
     followed by a refusal of C restores a state the server never held.

     The window is narrow — a menu closes on selection, so a second pick costs a
     re-open — but narrow is not closed, and a group control that can land on a
     value nobody chose is the failure this contract exists to prevent.

     Closing it needs ONE layer over the whole group, which means a group
     component to host it. That is a new public surface rather than a wiring
     detail, so it stays a candidate here instead of shipping something that
     looks enabled and can land on a value nobody chose. --}}
@props([
    'model' => null, // name of the Alpine variable holding the group's selected value
    'value' => null, // this option's value
    'disabled' => false,
    'shortcut' => null,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('dropdown.radio-item', $attributes->getAttributes());

    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $disabled = BooleanProp::from($disabled, false);

    // A radio menu item (WAI-ARIA menuitemradio). Selection is coordinated by a shared
    // Alpine variable named by `model` on an ancestor x-data — e.g.
    //   <div x-data="{ sort: 'name' }">
    //     <x-wirekit::dropdown.radio-item model="sort" value="name">Name</x-wirekit::dropdown.radio-item>
    // Clicking sets `sort = value`; the dot + aria-checked reflect `sort === value`.
    $classes = WireKit::resolveClasses('dropdown.radio-item', 'base', implode(' ', [
        'flex items-center gap-x-[var(--gap-wk-sm)] w-full',
        'px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-sm)]',
        'text-[length:var(--text-wk-md)] font-[family-name:var(--font-wk-sans)]',
        'text-[color:var(--color-wk-text)]',
        'transition-colors duration-[var(--transition-wk-duration)] ease-[var(--transition-wk-easing)]',
        // `focus-visible` + a ring, per dropdown/item.blade.php. The background
        // alone marks nothing: dark's --color-wk-bg-subtle equals its
        // --color-wk-bg-elevated, which is the panel this row sits on, so a
        // focused row moved 1.00:1 with the browser ring already suppressed.
        'focus:outline-hidden',
        'focus-visible:bg-[var(--color-wk-bg-subtle)]',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-inset',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'hover:bg-[var(--color-wk-bg-subtle)]',
        'cursor-pointer',
    ]), $scope);

    $disabledClasses = $disabled ? 'opacity-[var(--opacity-wk-disabled)] pointer-events-none' : '';
    $isBound = $model !== null && $value !== null;
@endphp

<button
    type="button"
    role="menuitemradio"
    tabindex="-1"
    @if($isBound)
        x-on:click="{{ $model }} = {{ \Pushery\WireKit\Support\AlpinePayload::from($value) }}"
        :aria-checked="{{ $model }} === {{ \Pushery\WireKit\Support\AlpinePayload::from($value) }} ? 'true' : 'false'"
    @else
        aria-checked="false"
    @endif
    @if($disabled) aria-disabled="true" @endif
    {{ $attributes->class([$classes, $disabledClasses]) }}
>
    {{-- Radio indicator — an ALWAYS-visible ring (so the whole group reads as a
         radio set, not just the selected row, and stays distinct from the
         checkbox-item's checkmark). Selected: the ring goes accent + a filled
         center dot appears. Reserves its slot so labels stay aligned. --}}
    <span class="shrink-0 w-4 h-4 flex items-center justify-center" aria-hidden="true">
        @if($isBound)
            <span
                class="w-3.5 h-3.5 rounded-full border-[length:var(--border-wk-width)] flex items-center justify-center transition-colors duration-[var(--transition-wk-duration)]"
                :class="{{ $model }} === {{ \Pushery\WireKit\Support\AlpinePayload::from($value) }} ? 'border-[var(--color-wk-accent)]' : 'border-[var(--color-wk-border-strong)]'"
            >
                <span x-show="{{ $model }} === {{ \Pushery\WireKit\Support\AlpinePayload::from($value) }}" x-cloak class="w-1.5 h-1.5 rounded-full bg-[var(--color-wk-accent)]"></span>
            </span>
        @else
            {{-- Static (non-model) radio item: a plain outline ring as the affordance. --}}
            <span class="w-3.5 h-3.5 rounded-full border-[length:var(--border-wk-width)] border-[var(--color-wk-border-strong)]"></span>
        @endif
    </span>

    {{ $slot }}

    @if($shortcut)
        <span class="ms-auto ps-[var(--padding-wk-x-md)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)] tabular-nums" aria-hidden="true">{{ $shortcut }}</span>
    @endif
</button>
