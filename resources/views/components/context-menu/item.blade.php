{{-- optimistic-ui: n/a — passthrough
     The menu owns open state; the item carries the developer's action. --}}
@props([
    'danger' => false,
    'disabled' => false,
    // Renders an `<a>` instead of a `<button>`. The docs promised this prop for a long time
    // while the component declared only the four below and hard-coded `<button>` — so `href`
    // fell into the attribute bag and was emitted onto the button, where HTML ignores it.
    // Left-click did nothing and cmd-click, middle-click, "open in new tab" and "copy link
    // address" were all dead, with no warning: a declared-looking prop on a real element.
    //
    // The shape mirrors `dropdown.item`, which has carried it all along — including the
    // `target="_blank"` handling, because a menu item that opens a new tab without
    // `rel="noopener"` hands the opened page a reference back to this one.
    'href' => null,
    'icon' => null,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('context-menu.item', $attributes->getAttributes());

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

    // `<a>` when a destination is given, `<button>` otherwise — the same rule dropdown.item
    // uses, so a developer moving between the two menus meets one behavior.
    $tag = $href ? 'a' : 'button';

    $targetAttr = $attributes->get('target', '');
    $opensNewTab = $href && str_contains($targetAttr, '_blank');
    $relAttr = $attributes->get('rel', '');
    $finalRel = $opensNewTab && ! str_contains($relAttr, 'noopener')
        ? trim($relAttr.' noopener noreferrer')
        : $relAttr;
    $computedRel = $opensNewTab ? $finalRel : ($relAttr ?: null);
@endphp

<{{ $tag }}
    @if($href) href="{{ $href }}" @endif
    @if($tag === 'button') type="{{ $attributes->get('type', 'button') }}" @endif
    role="menuitem"
    tabindex="-1"
    @if($disabled) aria-disabled="true" @endif
    @if($computedRel) rel="{{ $computedRel }}" @endif
    x-on:click="close()"
    {{ $attributes->except(['rel', 'type'])->class([$classes, $colorClasses, $disabledClasses]) }}
>
    @if($icon)
        <span class="shrink-0 w-5 h-5" aria-hidden="true">
            @if(function_exists('svg'))
                {{ svg(\Pushery\WireKit\WireKit::icon($icon), ['class' => 'w-5 h-5']) }}
            @endif
        </span>
    @endif

    {{ $slot }}
</{{ $tag }}>
