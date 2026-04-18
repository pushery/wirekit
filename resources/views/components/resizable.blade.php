@props([
    'direction' => 'horizontal',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Resizable — pure-CSS split panel layout.
    //
    // The interactive resize is delegated to the browser's native CSS `resize`
    // property (see `dist/wirekit.css` → "Resizable" section). No JavaScript,
    // no Alpine, no aria-valuenow tracking — every non-last panel exposes a
    // browser-native corner grip the user can drag, and the last panel uses
    // `flex: 1` to absorb whatever space the others leave behind.
    //
    // The accompanying `<x-wirekit::resizable.handle>` component renders a
    // thin styled divider line between panels for visual continuity, but it
    // is purely decorative — it has no JavaScript, no keyboard handler, and
    // does not participate in the resize logic.
    $classes = WireKit::resolveClasses('resizable', 'base', implode(' ', [
        'flex w-full',
        'font-[family-name:var(--font-wk-sans)]',
        'overflow-hidden',
        'rounded-[var(--radius-wk-lg)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
    ]), $scope);

    // flex-row / flex-col drives the visual orientation. The matching
    // data-wk-direction attribute drives the CSS selectors in
    // dist/wirekit.css that flip between `resize: horizontal` and
    // `resize: vertical` on the panels, plus the per-direction
    // `contain` rule that locks the wrapper against descendant growth.
    $directionClass = $direction === 'vertical' ? 'flex-col' : 'flex-row';
@endphp

<div
    data-wk-resizable
    data-wk-direction="{{ $direction === 'vertical' ? 'vertical' : 'horizontal' }}"
    {{ $attributes->class([$classes, $directionClass]) }}
>
    {{ $slot }}
</div>
