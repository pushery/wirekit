{{-- optimistic-ui: n/a — client-only
     Its state is which menu is open. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    // Whether each menu waits out of the page until it first opens. The default comes from the
    // configuration (`components.menubar.lazy`).
    'lazy' => config('wirekit.components.menubar.lazy', false),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('menubar', $attributes->getAttributes());

    // A caller's listener for an event this view listens to on the element the bag lands on
    // goes in the other spelling, so both run (Support\CallerListeners).
    $attributes = \Pushery\WireKit\Support\CallerListeners::beside($attributes, ['x-on:keydown']);

    // Menubar — horizontal menu bar with dropdown menus (File, Edit, View pattern).
    // Follows WAI-ARIA menubar pattern with arrow key navigation between menus.
    $classes = WireKit::resolveClasses('menubar', 'base', implode(' ', [
        'flex items-center gap-[var(--gap-wk-xs)]',
        'font-[family-name:var(--font-wk-sans)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-md)]',
        'p-[var(--padding-wk-y-xs)]',
        'shadow-[var(--shadow-wk-sm)]',
    ]), $scope);
    // A caller's `x-ref` belongs to the caller's component, and this bag lands on our root,
    // which would keep it: CallerRef::onRoot() hands it to the root above.
    $attributes = \Pushery\WireKit\Support\CallerRef::onRoot($attributes, holdsParts: true);

    // A lazy menubar holds each menu's teleport in a template of its own, which the browser keeps
    // out of the page until the menubar builds it as the menu first opens (utils/lazy-panel.js).
    // Each menu marks where its template opens and closes, and the menubar writes it there or
    // nothing from its own `lazy` (Support\LazyPanel).
    $lazy = \Pushery\WireKit\Support\BooleanProp::from($lazy, \Pushery\WireKit\Support\BooleanProp::from(config('wirekit.components.menubar.lazy', false)), 'menubar', 'lazy');
@endphp

<div
    x-data="wirekitMenubar()"
    x-on:keydown="handleKeydown"
    {{-- Outside-click close is handled in wirekitMenubar()'s document-level
         pointerdown listener, not here: the dropdown panels teleport to
         <body>, so a Blade x-on:click.outside on this root would fire when
         clicking inside an open panel (it's no longer a DOM descendant) and
         close the menu before the item's click registered. --}}
    @unless($attributes->has('role')) role="menubar" @endunless
    {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}
>
    {!! \Pushery\WireKit\Support\LazyPanel::resolve($slot->toHtml(), $lazy) !!}
</div>
