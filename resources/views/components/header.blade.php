@props([
    'sticky' => false,
    'container' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Header — top-level page header for app-shell layouts.
    $classes = WireKit::resolveClasses('header', 'base', implode(' ', [
        'flex items-center',
        'h-16',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-b border-[var(--color-wk-border)]',
        'px-[var(--padding-wk-x-lg)]',
        'gap-[var(--gap-wk-md)]',
    ]), $scope);

    $stickyClasses = $sticky ? 'sticky top-0 z-[var(--z-wk-sticky)]' : '';
@endphp

<header {{ $attributes->class([$classes, $stickyClasses]) }}>
    @if($container)
        <div class="flex items-center w-full max-w-[var(--size-wk-container-2xl,96rem)] mx-auto gap-[var(--gap-wk-md)]">
            {{ $slot }}
        </div>
    @else
        {{ $slot }}
    @endif
</header>
