@props([
    'model' => null, // name of the Alpine variable holding the group's selected value
    'value' => null, // this option's value
    'disabled' => false,
    'shortcut' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

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
        'focus:outline-none focus:bg-[var(--color-wk-bg-subtle)] hover:bg-[var(--color-wk-bg-subtle)]',
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
        x-on:click="{{ $model }} = @js($value)"
        :aria-checked="{{ $model }} === @js($value) ? 'true' : 'false'"
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
                :class="{{ $model }} === @js($value) ? 'border-[var(--color-wk-accent)]' : 'border-[var(--color-wk-border)]'"
            >
                <span x-show="{{ $model }} === @js($value)" x-cloak class="w-1.5 h-1.5 rounded-full bg-[var(--color-wk-accent)]"></span>
            </span>
        @else
            {{-- Static (non-model) radio item: a plain outline ring as the affordance. --}}
            <span class="w-3.5 h-3.5 rounded-full border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]"></span>
        @endif
    </span>

    {{ $slot }}

    @if($shortcut)
        <span class="ms-auto ps-[var(--padding-wk-x-md)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)] tabular-nums" aria-hidden="true">{{ $shortcut }}</span>
    @endif
</button>
