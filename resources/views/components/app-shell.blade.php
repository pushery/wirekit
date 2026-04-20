@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // App Shell — orchestrates header + sidebar + main layout.
    // Uses CSS grid to position sidebar and main content area.
    $classes = WireKit::resolveClasses('app-shell', 'base', implode(' ', [
        'flex flex-col',
        'min-h-screen',
        'bg-[var(--color-wk-bg)]',
        'text-[var(--color-wk-text)]',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);
@endphp

<div
    x-data="{ sidebarOpen: false }"
    {{ $attributes->class([$classes]) }}
>
    @isset($header)
        {{ $header }}
    @endisset

    <div class="flex flex-1 overflow-hidden">
        @isset($sidebar)
            {{-- Mobile backdrop --}}
            <div
                x-show="sidebarOpen"
                x-on:click="sidebarOpen = false"
                x-transition:enter="transition ease-out duration-[var(--transition-wk-duration)]"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-[var(--transition-wk-duration)]"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 z-[calc(var(--z-wk-sticky)+1)] bg-black/50 lg:hidden"
                x-cloak
            ></div>

            {{-- Sidebar --}}
            <aside
                x-bind:class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
                class="fixed inset-y-0 left-0 z-[calc(var(--z-wk-sticky)+2)] w-64 transform transition-transform duration-[var(--transition-wk-duration)] lg:relative lg:translate-x-0 lg:z-auto"
                x-cloak
                class:lg="!x-cloak"
            >
                {{ $sidebar }}
            </aside>
        @endisset

        {{ $slot }}
    </div>
</div>
