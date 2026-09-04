{{-- optimistic-ui: n/a — client-only
     Its state is the tree's expansion state. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    'label' => '',
    'icon' => null,
    'expanded' => false,
    'selected' => false,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('tree-view.node', $attributes->getAttributes());

    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\Support\DomId;
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

    // A screen reader announces the role and state of the element that RECEIVES focus,
    // and focus lands on the label row. With `role="treeitem"` one level up on the <li>,
    // a reader heard the label text alone — no "tree item, collapsed", and no
    // announcement when ArrowRight/ArrowLeft changed the expansion state. So the role,
    // aria-expanded, aria-selected and the roving tabindex all live on the row itself,
    // and the <li> steps out of the way with role="none" so the tree keeps owning
    // treeitems directly rather than list items that merely contain them.
    //
    // The child group is a block BENEATH the row rather than inside it — the row is a
    // flex line — so the DOM cannot express that the branch owns its children. aria-owns
    // restores it, which is the same shape the ARIA authoring practices use for a tree
    // whose rows are single elements.
    //
    // The id is COUNTED rather than random: a fresh id per render makes a Livewire morph
    // replace the node instead of patching it, and leaves the aria-owns written on the
    // previous render pointing at an element that no longer exists.
    $groupId = $hasChildren ? DomId::unique(null, 'wk-tree-group-') : null;
@endphp

<li
    role="none"
    {{ $attributes->class([$nodeClasses]) }}
    {{-- The toggle lives in resources/js/components/tree-view-node.js: it flips
         the flag AND writes aria-expanded onto the treeitem row inside this
         <li>, which is two statements — one more than Alpine's CSP build
         parses. --}}
    x-data="wirekitTreeViewNode({ expanded: {{ $expanded ? 'true' : 'false' }} })"
>
    {{-- Label row — click toggles expansion for branch nodes.
         Leaf nodes use margin-left instead of an inline spacer so the
         hover background does not cover empty whitespace. --}}
    {{-- Roving tabindex, the APG tree model: every row is `-1` and the container's
         Alpine factory promotes exactly one of them to `0` so the tree is a single tab
         stop. A `selected` row renders the `0` here as well, which is the hint the
         factory reads to pick the entry point — a reader who marked a node expects to
         land on it rather than on the first row. Left at a flat `-1`, the tree could
         only be entered by clicking a row, and the whole arrow model below it was dead
         for anyone using a keyboard. --}}
    <div
        class="{{ $labelClasses }}"
        role="treeitem"
        @if($hasChildren) aria-expanded="{{ $expanded ? 'true' : 'false' }}" aria-owns="{{ $groupId }}" @endif
        @if($selected) aria-selected="true" @endif
        tabindex="{{ $selected ? '0' : '-1' }}"
        data-wk-tree-node
        @if(!$hasChildren) style="margin-left: 1.25rem;" @endif
        @if($hasChildren)
            @click="toggle()"
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
            id="{{ $groupId }}"
            x-show="nodeExpanded"
            x-collapse
            class="list-none m-0 pl-4"
            style="list-style: none; margin: 0; padding-left: 1rem;"
        >
            {{ $slot }}
        </ul>
    @endif
</li>
