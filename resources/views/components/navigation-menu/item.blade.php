@props([
    'trigger' => null,
    'href' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Navigation menu item — either a simple link or a trigger with flyout panel.
    $name = \Illuminate\Support\Str::slug($trigger ?? 'nav-' . uniqid());

    $triggerClasses = WireKit::resolveClasses('navigation-menu.item', 'trigger', implode(' ', [
        'inline-flex items-center gap-1',
        'px-[var(--padding-wk-x-sm)]',
        'py-[var(--padding-wk-y-sm)]',
        'cursor-pointer',
        'text-[length:var(--text-wk-md)]',
        'text-[var(--color-wk-text)]',
        'rounded-[var(--radius-wk-sm)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'hover:bg-[var(--color-wk-bg-subtle)]',
        'focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
    ]), $scope);

    // Panel classes — the before: pseudo-element creates an invisible "bridge"
    // above the panel that covers the Floating UI offset gap (4px). Without it,
    // slow mouse movement from trigger to panel crosses a dead zone where neither
    // element receives hover, causing the panel to close prematurely.
    $panelClasses = WireKit::resolveClasses('navigation-menu.item', 'panel', implode(' ', [
        'fixed z-[var(--z-wk-dropdown)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-lg)]',
        'shadow-[var(--shadow-wk-lg)]',
        'p-[var(--padding-wk-x-lg)]',
        "before:content-[''] before:absolute before:-top-2 before:left-0 before:right-0 before:h-2",
    ]), $scope);

    $hasPanel = !$href;

    // Auto-inject rel="noopener noreferrer" + SR hint when target="_blank".
    // See dropdown/item.blade.php for rationale on except('rel') + explicit
    // rel render (avoids $attributes->merge treating rel as a default).
    $targetAttr = $attributes->get('target', '');
    $opensNewTab = $href && str_contains($targetAttr, '_blank');
    $relAttr = $attributes->get('rel', '');
    $finalRel = $opensNewTab && ! str_contains($relAttr, 'noopener')
        ? trim($relAttr.' noopener noreferrer')
        : $relAttr;
    $computedRel = $opensNewTab ? $finalRel : ($relAttr ?: null);
@endphp

@if($href)
    {{-- Simple link item (no flyout). The link text comes from the slot
         (the canonical pattern: `<x-wirekit::navigation-menu.item href="/x">Label</x-wirekit::navigation-menu.item>`)
         OR from the `trigger` prop (legacy / explicit-prop pattern). One
         of the two must be non-empty — otherwise the link has no
         accessible name and axe-core's "link-name" rule fails. We fall
         back from $slot to $trigger so authors who pass either form
         get a working link. --}}
    <a
        href="{{ $href }}"
        class="{{ $triggerClasses }}"
        @if($computedRel) rel="{{ $computedRel }}" @endif
        {{ $attributes->except('rel') }}
    >
        {{ trim((string) $slot) !== '' ? $slot : $trigger }}
        @if($opensNewTab)
            <span class="sr-only">(opens in new tab)</span>
        @endif
    </a>
@else
    {{-- Trigger + flyout panel --}}
    <div
        x-on:mouseenter="open('{{ $name }}')"
        x-on:mouseleave="scheduleClose()"
        class="relative"
        {{ $attributes }}
    >
        <button
            type="button"
            x-on:click="open('{{ $name }}')"
            :aria-expanded="activeItem === '{{ $name }}' ? 'true' : 'false'"
            aria-haspopup="dialog"
            data-wk-nav-trigger="{{ $name }}"
            class="{{ $triggerClasses }}"
        >
            {{ $trigger }}
            {{-- Chevron indicator --}}
            <svg class="w-4 h-4 text-[var(--color-wk-text-muted)] transition-transform duration-[var(--transition-wk-duration)]" :class="activeItem === '{{ $name }}' && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </button>

        {{-- Flyout panel --}}
        <div
            x-show="activeItem === '{{ $name }}'"
            x-on:mouseenter="cancelClose()"
            x-on:mouseleave="scheduleClose()"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-1"
            @keydown.escape.prevent="closeAll()"
            data-wk-nav-panel="{{ $name }}"
            class="{{ $panelClasses }}"
            x-cloak
        >
            {{ $slot }}
        </div>
    </div>
@endif
