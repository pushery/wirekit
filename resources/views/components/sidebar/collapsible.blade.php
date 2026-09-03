{{-- optimistic-ui: n/a — client-only
     Its state is disclosure state. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    'label' => '',
    'icon' => null,
    'open' => false,
    // Optional localStorage key. When set, the open/closed state survives a reload —
    // same semantics as the sidebar component's own `persist`. Null keeps it ephemeral.
    'persist' => null,
    // Trigger styling. 'default' looks like a sidebar.item (nav row). 'heading' makes
    // it a small uppercase tracked section label (matching a collapsible sidebar.group)
    // for designs that treat the group title as a section heading rather than a nav row.
    'variant' => 'default',
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('sidebar.collapsible', $attributes->getAttributes());

    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $open = BooleanProp::from($open, false);

    $variant = WireKit::validateProp('sidebar.collapsible', 'variant', $variant, ['default', 'heading']);

    // The `label` names the trigger BUTTON — it is that button's accessible name and
    // nothing else. So a section of four links reached assistive tech as a button
    // followed by four links, with nothing binding them together. These two values are
    // what binds them.
    //
    // Read THROUGH the bag rather than written before it. A hardcoded attribute followed
    // by the bag's own emits the attribute twice and the FIRST one wins, so a developer's
    // own name would be present in the markup and still never reach the reader — the
    // shape sidebar.toggle was corrected to for exactly that reason. A caller supplying
    // aria-labelledby gets no aria-label from us, so the two never compete for one name.
    $groupLabel = $attributes->has('aria-labelledby')
        ? null
        : ($attributes->get('aria-label') ?: ((string) $label !== '' ? $label : null));

    // The role is coupled to the NAME instead of being emitted unconditionally. A
    // role="group" nothing can name announces a boundary and then cannot say what the
    // boundary is for — noise in the accessibility tree of every page that uses one.
    // bento-grid and accordion already decided this, each held by its own red-proof.
    $groupRole = $attributes->get(
        'role',
        $groupLabel !== null || $attributes->has('aria-labelledby') ? 'group' : null
    );

    // The disclosed region needs an id so the trigger's `aria-controls` can name it —
    // the wiring the standalone <x-wirekit::collapsible> already carries.
    //
    // Seeded from the label, falling back to a bare icon name, because a per-render
    // random id makes Livewire's morph treat the region as a NEW node on every round
    // trip: it replaces the live node with a clone, and x-collapse replays its height
    // transition instead of holding the open height. With neither a label nor an icon
    // there is nothing stable to derive from, and the random suffix remains on purpose —
    // a collision between two anonymous widgets on one page is worse than a re-render.
    //
    // Suffixed rather than reused: the bag emits a caller-supplied `id` on the ROOT, and
    // two elements sharing one id is a defect of its own.
    $panelId = ($attributes->get('id') ?: WireKit::stableId(
        'wk-sidebar-collapsible',
        (string) $label !== ''
            ? (string) $label
            : (is_string($icon) && ! str_contains($icon, '<') ? $icon : null)
    )).'-panel';

    // Collapsible sidebar group — a disclosure widget that toggles child items.
    // The default trigger looks like a sidebar item but acts as an expand/collapse
    // toggle. Uses aria-expanded for AT, and indents child content by one level.
    $triggerClasses = WireKit::resolveClasses('sidebar.collapsible', 'trigger', implode(' ', [
        'flex items-center gap-[var(--padding-wk-x-sm)] w-full',
        'group-data-[collapsed]/wk-sidebar:justify-center',
        'px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)]',
        // Same row-height floor as sidebar.item, and for the same reason: the label carries
        // `sr-only` in the collapsed rail, so without a floor this row is sized by its
        // LABEL while expanded and by its ICON while collapsed — 2.75px per row on the
        // shipped type ramp, which moves everything below the rail when it collapses.
        'min-h-[calc(1lh_+_var(--padding-wk-y-sm)_*_2)]',
        'rounded-[var(--radius-wk-nav-item)]',
        'text-[color:var(--color-wk-text-muted)]',
        'hover:bg-[var(--color-wk-bg-muted)]',
        'hover:text-[color:var(--color-wk-text)]',
        'focus-visible:outline-none',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'cursor-pointer',
    ]), $scope);

    // The 'heading' variant lives in its OWN resolvable block so a theme can restyle
    // just the heading typography WITHOUT copying the ~12 default-trigger classes and
    // drifting on the next release. Mirrors sidebar.group's collapsible-trigger tokens
    // (small uppercase tracked label) while keeping the icon + label + chevron layout.
    $headingTriggerClasses = WireKit::resolveClasses('sidebar.collapsible', 'trigger-heading', implode(' ', [
        'flex items-center gap-[var(--padding-wk-x-sm)] w-full',
        'group-data-[collapsed]/wk-sidebar:justify-center',
        'px-[var(--padding-wk-x-sm)] pt-[var(--padding-wk-y-sm)] pb-[2px]',
        'text-[length:var(--text-wk-xs)]',
        'font-[number:var(--font-wk-heading-weight)]',
        'uppercase tracking-wider',
        'text-[color:var(--color-wk-text-subtle)]',
        'hover:text-[color:var(--color-wk-text-muted)]',
        'focus-visible:outline-none',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'rounded-[var(--radius-wk-md)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'cursor-pointer',
    ]), $scope);

    $triggerClasses = $variant === 'heading' ? $headingTriggerClasses : $triggerClasses;

    // Child container — indented to show hierarchy, with a guide line down the indent.
    //
    // The line is what makes a nested list read as nested at a glance. Indentation alone
    // is ambiguous once a label wraps: a second line of text starts at the same x as a
    // child item, and the eye cannot tell a wrapped parent from a child. Every reference
    // console draws this line for exactly that reason.
    //
    // It uses the LOGICAL inline-start edge (`border-s`, `ms-`, `ps-`) so a right-to-left
    // document gets the guide on the side its hierarchy actually grows from. Purely
    // decorative: the structure is already carried by aria-expanded on the trigger and by
    // the items being inside the disclosed region, so nothing is lost when it is not seen.
    $childClasses = WireKit::resolveClasses('sidebar.collapsible', 'children', implode(' ', [
        'flex flex-col gap-[2px]',
        'ms-[var(--padding-wk-x-sm)]',
        'ps-[var(--padding-wk-x-md)]',
        'border-s-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
    ]), $scope);
@endphp

<div
    x-data="wirekitSidebarDisclosure({ open: {{ $open ? 'true' : 'false' }}, persist: {{ $persist === null ? 'null' : \Pushery\WireKit\Support\AlpinePayload::from($persist) }} })"
    @if($groupRole) role="{{ $groupRole }}" @endif
    @if($groupLabel !== null) aria-label="{{ $groupLabel }}" @endif
    {{ $attributes->except(['role', 'aria-label']) }}
>
    {{-- Trigger button — toggles the child items. aria-expanded announces
         the current state to screen readers. --}}
    {{-- In the collapsed icon rail the disclosure trigger is `hidden`: the heading
         (icon + label + chevron) is unreadable at 3.5rem and there is nothing to
         disclose there — the children are force-shown as a flat icon list below,
         matching the static sidebar.group's rail behavior. --}}
    <button
        type="button"
        x-on:click="toggle()"
        :aria-expanded="open ? 'true' : 'false'"
        aria-controls="{{ $panelId }}"
        {{-- With no label the trigger's only contents are the aria-hidden icon and the
             aria-hidden chevron, so it reaches a screen reader as a bare "button"
             (WCAG 4.1.2) — the same class as the sidebar's own collapse toggle. Fall
             back to a generic name, exactly as sidebar.group does for its twin.
             A caller's `aria-label` cannot serve here: it is read through the bag above
             and names the ROOT, not this button. --}}
        @if((string) $label === '') aria-label="{{ __('wirekit::Section') }}" @endif
        class="{{ $triggerClasses }} group-data-[collapsed]/wk-sidebar:hidden group-data-[settling]/wk-sidebar:hidden"
    >
        @if($icon)
            {{-- Icon — decorative, hidden from AT. A bare name string resolves
                 via the WireKit icon system (consistent with sidebar.item /
                 dropdown.item); a <x-slot:icon> or inline markup (non-string
                 ComponentSlot, Htmlable) renders verbatim. --}}
            <span class="shrink-0" aria-hidden="true">
                @if(is_string($icon) && ! str_contains($icon, '<') && function_exists('svg'))
                    {{ svg(\Pushery\WireKit\WireKit::icon($icon), ['class' => 'w-5 h-5']) }}
                @else
                    {{ $icon }}
                @endif
            </span>
        @endif
        {{-- Wraps rather than truncating — see sidebar.item for the rule. --}}
        <span class="flex-1 break-words text-left group-data-[collapsed]/wk-sidebar:sr-only group-data-[settling]/wk-sidebar:sr-only">{{ $label }}</span>
        @isset($trailing)
            {{-- Anything the caller wants at the end of the trigger.
                 A group is collapsed to keep the list short — and if it contains items with
                 counters, those counters vanish with it. `persist` makes that permanent:
                 collapse once and the numbers are never seen again without going to look.
                 Collapsing stops being a decision about space and becomes one that destroys
                 information.
                 A slot rather than a `badge` prop, and the difference is not academic. The
                 case that surfaced this wanted a SILENT DOT, not a sum: a total across three
                 work queues would have asserted an urgency the number cannot know. A prop
                 that takes a count cannot express "something is there" without saying how
                 much.
                 Alpine's `open` is in scope here, so the caller decides WHEN it shows —
                 `x-show="! open"` gives the common case, where the individual counters are
                 already visible once the group is expanded and a second summary of the same
                 quantity is one too many. Deliberately not decided for them: a dot that
                 means "unread" reads differently from one that means "attention", and only
                 one of those is redundant when expanded. --}}
            <span class="shrink-0 group-data-[collapsed]/wk-sidebar:hidden group-data-[settling]/wk-sidebar:hidden">{{ $trailing }}</span>
        @endisset
        {{-- Chevron indicator — rotates when open; hidden in the collapsed rail. --}}
        <svg
            class="w-3.5 h-3.5 shrink-0 transition-transform duration-[var(--transition-wk-duration)] group-data-[collapsed]/wk-sidebar:hidden group-data-[settling]/wk-sidebar:hidden"
            :class="open ? 'rotate-90' : ''"
            fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"
        >
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
    </button>

    {{-- Collapsible children — shown/hidden with Alpine when the sidebar is expanded.
         In the collapsed rail they are FORCE-SHOWN as a flat, centered icon list
         (the indent is dropped via pl-0) instead of being hidden: the section icons
         stay reachable, matching the static sidebar.group. The `typeof collapsed`
         guard is mandatory — a sidebar.collapsible used inside a NON-collapsible
         <x-wirekit::sidebar> has no `collapsed` in Alpine scope, so a bare
         `open || collapsed` would throw a ReferenceError there. --}}
    <div id="{{ $panelId }}" x-show="childrenVisible()" x-collapse x-cloak class="{{ $childClasses }} group-data-[collapsed]/wk-sidebar:ms-0 group-data-[collapsed]/wk-sidebar:ps-0 group-data-[collapsed]/wk-sidebar:border-s-0">
        {{ $slot }}
    </div>
</div>
