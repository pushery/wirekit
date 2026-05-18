@props([
    'label' => '',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Menu trigger button + dropdown panel container.
    $name = \Illuminate\Support\Str::slug($label);

    $triggerClasses = WireKit::resolveClasses('menubar.menu', 'trigger', implode(' ', [
        'px-[var(--padding-wk-x-sm)]',
        'py-[var(--padding-wk-y-xs)]',
        'cursor-pointer',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text)]',
        'rounded-[var(--radius-wk-sm)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'hover:bg-[var(--color-wk-bg-subtle)]',
        'focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
    ]), $scope);

    $panelClasses = WireKit::resolveClasses('menubar.menu', 'panel', implode(' ', [
        'fixed z-[var(--z-wk-dropdown)]',
        'min-w-[12rem]',
        'py-[var(--padding-wk-y-xs)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-md)]',
        'shadow-[var(--shadow-wk-md)]',
        'overflow-hidden',
    ]), $scope);
@endphp

<div class="relative" {{ $attributes }}>
    {{-- Menu trigger --}}
    <button
        type="button"
        x-on:click="toggleMenu('{{ $name }}')"
        x-on:mouseenter="openMenu('{{ $name }}')"
        :aria-expanded="activeMenu === '{{ $name }}' ? 'true' : 'false'"
        aria-haspopup="menu"
        role="menuitem"
        data-wk-menubar-trigger="{{ $name }}"
        class="{{ $triggerClasses }}"
    >
        {{ $label }}
    </button>

    {{-- Dropdown panel --}}
    <div
        x-show="activeMenu === '{{ $name }}'"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        data-wk-menubar-panel="{{ $name }}"
        role="menu"
        class="{{ $panelClasses }}"
        x-cloak
    >
        {{ $slot }}
    </div>
</div>
