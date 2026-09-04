{{-- optimistic-ui: n/a — client-only
     Its state is which menu is open. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('navigation-menu', $attributes->getAttributes());

    // Navigation Menu — top-level nav with rich flyout panels (mega menu).
    // Uses disclosure pattern: hover or click to reveal content panels.
    $classes = WireKit::resolveClasses('navigation-menu', 'base', implode(' ', [
        'relative flex items-center gap-1',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);
@endphp

<nav
    x-data="wirekitNavigationMenu()"
    {{-- Outside-click close is handled in wirekitNavigationMenu()'s
         document-level pointerdown listener, not here: the flyout panels
         teleport to <body>, so a Blade x-on:click.outside on this root would
         fire when clicking inside an open panel (no longer a DOM descendant)
         and close it before an in-panel click registered. --}}
    {{-- One keydown listener for the whole bar rather than one per item: a
         key pressed on a plain link item bubbles here just as one pressed on a
         flyout trigger does, so Arrow Left/Right and Home/End move across BOTH
         kinds of top-level item. The handler resolves the trigger (and with it
         the panel name) from the event target. The panels are teleported to the
         overlay root, so their own keys never bubble here — they carry a
         separate listener in navigation-menu/item.blade.php. --}}
    x-on:keydown="handleBarKeydown($event)"
    x-on:focusout="handleBarFocusOut($event)"
    aria-label="{{ __('wirekit::Main navigation') }}"
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</nav>
