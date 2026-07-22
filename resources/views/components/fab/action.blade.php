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

    // An icon-only action with no label has no accessible name at all — a menu
    // item a screen reader can only announce as "button". Nothing downstream can
    // recover that, so say it where the developer will see it (throws in debug,
    // logs in production — the house strictness gate).
    if ($label === '' && $icon !== null) {
        WireKit::validateProp('fab.action', 'label', '', ['a non-empty label describing the action']);
    }

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
    {{-- Only emit the name when there IS one. `aria-label=""` is worse than no
         aria-label at all: it suppresses the fallback to the element's own text
         content, so an action rendering a text slot would lose the name it
         already had. With no label AND no text (the icon-only case) nothing can
         invent one — the strict-mode validation below says so out loud instead of
         shipping a nameless menu item. --}}
    @if($label !== '') aria-label="{{ $label }}" @endif
    data-wk-fab-action
    {{ $attributes->class([$classes]) }}
>
    @if($icon)
        <x-wirekit::icon :name="$icon" class="h-5 w-5" />
    @else
        {{ $slot }}
    @endif
</{{ $tag }}>
