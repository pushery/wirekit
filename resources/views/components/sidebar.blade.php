@props([
    // Collapse-to-icon rail. When true, the sidebar can collapse to a narrow
    // icon-only rail: an auto-rendered toggle flips the state, item/group labels
    // become sr-only (accessible names preserved), and only icons stay visible.
    'collapsible' => false,
    // Initial collapsed state (only meaningful with `collapsible`).
    'collapsed' => false,
    // Optional localStorage key — persists the collapsed state across reloads.
    'persist' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Sidebar root: a semantic <nav> landmark that holds grouped navigation
    // items. Uniform `p-[var(--padding-wk-y-sm)]` (= 0.375rem all four
    // sides) so the OUTER gap between the sidebar border and each item's
    // hover/active highlight matches the INNER gap between the item edge
    // and the label text. With asymmetric `px-x-sm py-y-sm` (0.625rem
    // horizontal, 0.375rem vertical) the outer gap looked 67% larger
    // than the inner — visually unbalanced.
    $classes = WireKit::resolveClasses('sidebar', 'base', implode(' ', [
        'flex flex-col',
        'gap-[var(--space-wk-sm)]',
        'p-[var(--padding-wk-y-sm)]',
        'text-[length:var(--text-wk-sm)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-lg)]',
    ]), $scope);

    // Collapse toggle button — only rendered (and only styled) for a collapsible
    // sidebar. Sits at the inline-end so it reads as a chrome control.
    $collapseBtnClasses = implode(' ', [
        'self-end inline-flex items-center justify-center shrink-0',
        'p-1 rounded-[var(--radius-wk-sm)]',
        'text-[color:var(--color-wk-text-muted)]',
        'hover:bg-[var(--color-wk-bg-muted)] hover:text-[color:var(--color-wk-text)]',
        'focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
        'transition-colors duration-[var(--transition-wk-duration)] cursor-pointer',
        // In the collapsed rail the button centers with the icons.
        'group-data-[collapsed]/wk-sidebar:self-center',
    ]);
@endphp

@if($collapsible)
    {{-- Collapsible rail. The `collapsed` state lives here; `group/wk-sidebar` +
         the `data-collapsed` marker let descendant labels/indicators react via
         pure CSS (`group-data-[collapsed]/wk-sidebar:*`) — no per-item Alpine.
         3.5rem is the structural icon-rail width (icon + padding), not a theme
         value, so it stays a literal like the structural w-9 day cells. --}}
    <nav
        aria-label="Sidebar"
        x-data="{ collapsed: {{ $collapsed ? 'true' : 'false' }}, _key: @js($persist), init() { if (this._key) { try { const s = localStorage.getItem(this._key); if (s !== null) this.collapsed = s === '1'; } catch (e) {} } }, toggle() { this.collapsed = ! this.collapsed; if (this._key) { try { localStorage.setItem(this._key, this.collapsed ? '1' : '0'); } catch (e) {} } } }"
        :data-collapsed="collapsed ? '' : null"
        :class="collapsed ? 'w-[3.5rem]' : 'w-[var(--wk-sidebar-w,16rem)]'"
        {{ $attributes->class([$classes, 'group/wk-sidebar transition-[width] duration-[var(--transition-wk-duration)]']) }}
    >
        <button
            type="button"
            x-on:click="toggle()"
            :aria-expanded="collapsed ? 'false' : 'true'"
            :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
            class="{{ $collapseBtnClasses }}"
        >
            <svg class="h-5 w-5 transition-transform duration-[var(--transition-wk-duration)]" :class="collapsed ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.75 4.5 11.25 12l7.5 7.5m-7.5-15L3.75 12l7.5 7.5" />
            </svg>
        </button>
        {{ $slot }}
    </nav>
@else
    {{-- <nav aria-label="Sidebar"> — named so AT distinguishes it from the main nav. --}}
    <nav aria-label="Sidebar" {{ $attributes->class([$classes]) }}>
        {{ $slot }}
    </nav>
@endif
