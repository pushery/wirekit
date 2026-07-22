@props([
    // What this bar navigates. Every page has more than one nav landmark once you
    // count the header, so an unnamed one is announced as "navigation" and the
    // reader has to go in to find out which.
    'label' => __('Main'),
    // Hide the bar from `md` up, where a sidebar or a header does this job. A bar
    // pinned to the bottom of a 27-inch screen is a long way from the content it
    // belongs to.
    'mobileOnly' => true,
    // Let the bar track the current tab client-side: clicking an item marks it
    // active (Alpine), no page load. Off by default — a real app usually drives
    // `active` from its router. Handy for a self-contained demo. Read by each item
    // via @aware.
    'interactive' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $isInteractive = filter_var($interactive, FILTER_VALIDATE_BOOLEAN);

    $classes = WireKit::resolveClasses('bottom-nav', 'base', implode(' ', [
        'wk-bottom-nav',
        'fixed inset-x-0 bottom-0 z-40',
        'flex items-stretch justify-around',
        'border-t-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'font-[family-name:var(--font-wk-sans)]',
        $mobileOnly ? 'md:hidden' : '',
    ]), $scope);
@endphp

{{-- A real <nav> landmark, so a screen-reader user can jump straight to it.

     The safe-area padding lives in dist/wirekit.css: on a phone with a home
     indicator, the bottom of the viewport is not the bottom of the usable screen,
     and a bar that ignores that puts its labels under the indicator. --}}
<nav
    aria-label="{{ $label }}"
    data-wk-bottom-nav
    @if($isInteractive) x-data="{ active: null }" @endif
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</nav>
