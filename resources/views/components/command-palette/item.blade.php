@props([
    'href' => null,
    'icon' => null,
    'shortcut' => null,
    'disabled' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Command item — selectable option in the command palette.
    $itemId = 'wk-cmd-item-' . \Illuminate\Support\Str::random(6);

    $classes = WireKit::resolveClasses('command-palette.item', 'base', implode(' ', [
        'flex items-center gap-x-[var(--gap-wk-sm)] w-full',
        'px-[var(--padding-wk-x-md)]',
        'py-[var(--padding-wk-y-sm)]',
        'text-[length:var(--text-wk-md)]',
        'text-[var(--color-wk-text)]',
        'font-[family-name:var(--font-wk-sans)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'cursor-pointer',
        'hover:bg-[var(--color-wk-bg-subtle)]',
        'focus:outline-none focus:bg-[var(--color-wk-bg-subtle)]',
        'data-[active=true]:bg-[var(--color-wk-bg-subtle)]',
    ]), $scope);

    $disabledClasses = $disabled
        ? 'opacity-[var(--opacity-wk-disabled)] pointer-events-none'
        : '';

    $tag = $href ? 'a' : 'button';
@endphp

<{{ $tag }}
    id="{{ $itemId }}"
    @if($href) href="{{ $href }}" @endif
    @if($tag === 'button') type="button" @endif
    role="option"
    tabindex="-1"
    @if($disabled) aria-disabled="true" @endif
    {{ $attributes->class([$classes, $disabledClasses]) }}
>
    @if($icon)
        <span class="shrink-0 w-5 h-5 text-[var(--color-wk-text-muted)]" aria-hidden="true">
            @if(function_exists('svg'))
                {{ svg(\Pushery\WireKit\WireKit::icon($icon), ['class' => 'w-5 h-5']) }}
            @endif
        </span>
    @endif

    <span class="flex-1 truncate">{{ $slot }}</span>

    @if($shortcut)
        <span class="ml-auto flex items-center gap-1 text-[length:var(--text-wk-xs)] text-[var(--color-wk-text-muted)]" aria-hidden="true">
            @foreach((array) $shortcut as $key)
                <kbd class="inline-flex items-center justify-center min-w-5 px-1.5 py-0.5 rounded-[var(--radius-wk-sm)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] bg-[var(--color-wk-bg-muted)] font-[family-name:var(--font-wk-mono)] text-[length:var(--text-wk-xs)]">{{ $key }}</kbd>
            @endforeach
        </span>
    @endif
</{{ $tag }}>
