@props([
    // What this action does. The button is an icon, so this is its whole name.
    'label' => '',
    'icon' => null,
    'href' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $tag = $href ? 'a' : 'button';

    $classes = WireKit::resolveClasses('fab.action', 'base', implode(' ', [
        'wk-fab-action',
        'flex h-11 w-11 cursor-pointer items-center justify-center rounded-[var(--radius-wk-full)]',
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'bg-[var(--color-wk-bg-elevated)] text-[color:var(--color-wk-text)]',
        'shadow-[var(--shadow-wk-md)]',
        'transition-colors duration-[var(--transition-wk-duration)]',
        'hover:bg-[var(--color-wk-bg-subtle)]',
        'focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-offset-2',
    ]), $scope);
@endphp

{{-- role="menuitem" to match the parent's role="menu": the two have to agree, or
     a screen reader announces a popup whose contents are not menu items.

     h-11/w-11 is 44px — the touch minimum, and these are the smallest targets on
     the screen. --}}
<{{ $tag }}
    @if($href) href="{{ $href }}" @else type="button" @endif
    role="menuitem"
    aria-label="{{ $label }}"
    data-wk-fab-action
    {{ $attributes->class([$classes]) }}
>
    @if($icon)
        <x-wirekit::icon :name="$icon" class="h-5 w-5" />
    @else
        {{ $slot }}
    @endif
</{{ $tag }}>
