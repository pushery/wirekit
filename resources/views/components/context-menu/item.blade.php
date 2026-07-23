@props([
    'danger' => false,
    'disabled' => false,
    'icon' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $danger = BooleanProp::from($danger, false);
    $disabled = BooleanProp::from($disabled, false);

    // Context menu item — same visual pattern as dropdown items.
    // whitespace-nowrap: menu items must never wrap onto a second line. Without it,
    // labels longer than the panel's min-w (12rem) get broken onto two lines instead
    // of growing the panel horizontally — that breaks the menu's visual rhythm and
    // contradicts standard menu conventions. The panel's outer container already has
    // max-w-[calc(100vw-1rem)] safety, so overly long labels get clipped at the
    // viewport edge rather than overflowing.
    $classes = WireKit::resolveClasses('context-menu.item', 'base', implode(' ', [
        'flex items-center gap-x-[var(--gap-wk-sm)] w-full',
        'px-[var(--padding-wk-x-md)]',
        'py-[var(--padding-wk-y-sm)]',
        'text-[length:var(--text-wk-md)]',
        'font-[family-name:var(--font-wk-sans)]',
        'whitespace-nowrap',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
        'focus:outline-none',
        'focus:bg-[var(--color-wk-bg-subtle)]',
        'cursor-pointer',
    ]), $scope);

    $colorClasses = $danger
        ? 'text-[color:var(--color-wk-danger-text)] hover:bg-[var(--color-wk-bg-subtle)]'
        : 'text-[color:var(--color-wk-text)] hover:bg-[var(--color-wk-bg-subtle)]';

    $disabledClasses = $disabled
        ? 'opacity-[var(--opacity-wk-disabled)] pointer-events-none'
        : '';
@endphp

<button
    type="button"
    role="menuitem"
    tabindex="-1"
    @if($disabled) aria-disabled="true" @endif
    x-on:click="close()"
    {{ $attributes->class([$classes, $colorClasses, $disabledClasses]) }}
>
    @if($icon)
        <span class="shrink-0 w-5 h-5" aria-hidden="true">
            @if(function_exists('svg'))
                {{ svg(\Pushery\WireKit\WireKit::icon($icon), ['class' => 'w-5 h-5']) }}
            @endif
        </span>
    @endif

    {{ $slot }}
</button>
