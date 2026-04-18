@props([
    'id' => null,
    'title' => '',
    'scope' => null,
])

@php
    use Illuminate\Support\Str;
    use Pushery\WireKit\WireKit;

    // Each item needs a stable id so Alpine can track open/closed state in the
    // parent's `opened` array, and so aria-controls / aria-labelledby can pair.
    $itemId = $id ?? 'wk-accordion-item-' . Str::random(6);
    $buttonId = $itemId . '-button';
    $panelId = $itemId . '-panel';

    // Trigger button — full-width, left-aligned, clickable row that toggles
    // the item and swaps the chevron direction based on open state.
    $buttonClasses = WireKit::resolveClasses('accordion.item', 'button', implode(' ', [
        'flex items-center justify-between w-full gap-[var(--padding-wk-x-md)]',
        'px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)]',
        'text-left',
        'text-[length:var(--text-wk-md)]',
        'text-[var(--color-wk-text)]',
        'font-[number:var(--font-wk-heading-weight)]',
        'hover:bg-[var(--color-wk-bg-muted)]',
        'focus-visible:outline-none',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'focus-visible:ring-inset',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'cursor-pointer',
    ]), $scope);

    // Panel — revealed content region. Uses aria role="region" + aria-labelledby
    // so AT announces it as a titled region when user arrows into it.
    $panelClasses = WireKit::resolveClasses('accordion.item', 'panel', implode(' ', [
        'px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)]',
        'text-[length:var(--text-wk-sm)]',
        'text-[var(--color-wk-text-muted)]',
        'border-t-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
    ]), $scope);

    // Decorative chevron — rotates 180° when item is open. aria-hidden
    // because the open state is already conveyed by aria-expanded on the button.
    $chevronClasses = 'w-4 h-4 text-[var(--color-wk-text-subtle)] transition-transform duration-[var(--transition-wk-duration)]';
@endphp

<div {{ $attributes }}>
    {{-- Header button: delegates to toggle() defined on the parent accordion's x-data scope.
         Alpine resolves methods via scope chain — no $root prefix needed. --}}
    <h3>
        <button
            type="button"
            id="{{ $buttonId }}"
            aria-controls="{{ $panelId }}"
            :aria-expanded="isOpen(@js($itemId)) ? 'true' : 'false'"
            @click="toggle(@js($itemId))"
            class="{{ $buttonClasses }}"
        >
            <span>{{ $title !== '' ? $title : ($header ?? '') }}</span>
            <svg
                class="{{ $chevronClasses }}"
                :class="isOpen(@js($itemId)) ? 'rotate-180' : ''"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
            >
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.06l3.71-3.83a.75.75 0 111.08 1.04l-4.25 4.39a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
        </button>
    </h3>

    {{-- Panel: only rendered when open. x-show toggles display; role=region
         pairs with aria-labelledby so screen readers know which heading names it. --}}
    <div
        id="{{ $panelId }}"
        role="region"
        aria-labelledby="{{ $buttonId }}"
        x-show="isOpen(@js($itemId))"
        x-cloak
        class="{{ $panelClasses }}"
    >
        {{ $slot }}
    </div>
</div>
