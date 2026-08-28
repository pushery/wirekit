{{-- optimistic-ui: n/a — client-only
     It collapses and expands the sidebar. No server is asked. --}}
@props([
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('sidebar.toggle', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // Sidebar Toggle — hamburger button that toggles sidebar visibility.
    // Reads `sidebarOpen` from app-shell's x-data.
    $classes = WireKit::resolveClasses('sidebar.toggle', 'base', implode(' ', [
        'inline-flex items-center justify-center',
        'p-2',
        'rounded-[var(--radius-wk-sm)]',
        'text-[color:var(--color-wk-text-muted)]',
        'hover:bg-[var(--color-wk-bg-subtle)]',
        'hover:text-[color:var(--color-wk-text)]',
        'focus-visible:outline-none',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'transition-colors',
        'cursor-pointer',
    ]), $scope);

    // Accessible name: translatable default, but a caller-supplied aria-label
    // wins — and is emitted exactly once. Rendering the default AND letting the
    // attribute bag add the caller's value produced two aria-label attributes,
    // the hardcoded English one winning.
    $ariaLabel = $attributes->get('aria-label', __('Toggle sidebar'));
@endphp

<button
    type="button"
    {{-- The marker app-shell looks for. A shell that turns its navigation into an
         off-canvas drawer below the breakpoint owes that drawer an opener, and the only
         way it can tell whether one is present is to look for it in the slots it was
         handed. Renaming or removing this attribute makes that check blind. --}}
    data-wk-sidebar-toggle
    x-on:click="sidebarOpen = !sidebarOpen"
    :aria-expanded="sidebarOpen ? 'true' : 'false'"
    {{-- Names the drawer this opens. Read through `$data` rather than as a bare
         `drawerId`, because a bare reference throws where the property does not exist —
         and it legitimately does not: a shell whose `x-data` a developer replaced, or a
         toggle placed outside one. A missing `aria-controls` is a smaller defect than a
         console error that stops the rest of the expression. --}}
    :aria-controls="$data.drawerId || null"
    aria-label="{{ $ariaLabel }}"
    {{ $attributes->except('aria-label')->class([$classes]) }}
>
    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
    </svg>
</button>
