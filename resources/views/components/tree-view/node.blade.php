@props([
    'label' => '',
    'icon' => null,
    'expanded' => false,
    'selected' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $expanded = BooleanProp::from($expanded, false);
    $selected = BooleanProp::from($selected, false);

    // Tree node: each item is a treeitem with optional nested group.
    // Indentation is handled by nested <ul> elements — browser + screen reader
    // naturally convey the nesting level.
    $nodeClasses = WireKit::resolveClasses('tree-view.node', 'base', implode(' ', [
        'list-none',
    ]), $scope);

    // The clickable label row. Uniform padding matches the
    // `sidebar/item.blade.php` sibling shape (same internal-element
    // visual rhythm for any list-style item inside a navigation
    // wrapper).
    $labelClasses = implode(' ', [
        'flex items-center gap-1',
        'px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)]',
        'rounded-[var(--radius-wk-sm)]',
        'cursor-pointer select-none',
        'hover:bg-[var(--color-wk-bg-muted)]',
        'focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
        'transition-colors duration-[var(--transition-wk-duration)]',
    ]);

    $hasChildren = $slot->isNotEmpty();
    $nodeId = 'wk-tree-' . \Illuminate\Support\Str::random(6);
@endphp

<li
    role="treeitem"
    @if($hasChildren) aria-expanded="{{ $expanded ? 'true' : 'false' }}" @endif
    @if($selected) aria-selected="true" @endif
    {{ $attributes->class([$nodeClasses]) }}
    x-data="{ nodeExpanded: {{ $expanded ? 'true' : 'false' }} }"
>
    {{-- Label row — click toggles expansion for branch nodes.
         Leaf nodes use margin-left instead of an inline spacer so the
         hover background does not cover empty whitespace. --}}
    <div
        class="{{ $labelClasses }}"
        tabindex="-1"
        data-wk-tree-node
        @if(!$hasChildren) style="margin-left: 1.25rem;" @endif
        @if($hasChildren)
            @click="nodeExpanded = !nodeExpanded; $el.closest('[role=treeitem]').setAttribute('aria-expanded', nodeExpanded)"
        @endif
    >
        {{-- Expand/collapse chevron (only for branch nodes) --}}
        @if($hasChildren)
            <svg
                aria-hidden="true"
                class="h-4 w-4 shrink-0 text-[color:var(--color-wk-text-muted)] transition-transform duration-[var(--transition-wk-duration)]"
                :class="nodeExpanded ? 'rotate-90' : ''"
                viewBox="0 0 16 16"
                fill="currentColor"
            >
                <path d="M6 3l5 5-5 5V3z"/>
            </svg>
        @endif

        {{-- Optional icon --}}
        @if($icon)
            <x-wirekit::icon :name="$icon" size="sm" class="shrink-0" />
        @endif

        {{-- Node label text --}}
        <span class="truncate">{{ $label }}</span>
    </div>

    {{-- Nested children group --}}
    @if($hasChildren)
        <ul
            role="group"
            x-show="nodeExpanded"
            x-collapse
            class="list-none m-0 pl-4"
            style="list-style: none; margin: 0; padding-left: 1rem;"
        >
            {{ $slot }}
        </ul>
    @endif
</li>
