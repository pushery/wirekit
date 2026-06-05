@props([
    'mode' => config('wirekit.components.accordion.mode', 'single'),
    // Visual treatment of the container + items:
    //   - 'bordered'  → outer border + rounded card + row dividers + elevated bg
    //                   (default; byte-identical to the pre-variant look).
    //   - 'flush'     → no outer chrome, just hair-line row dividers — for an
    //                   FAQ that sits inline in page content.
    //   - 'separated' → each item is its own standalone card with a gap between
    //                   them (the container draws nothing; items carry the chrome).
    'variant' => config('wirekit.components.accordion.variant', 'bordered'),
    // Row density. 'md' is the default trigger/panel padding; 'lg' is roomier
    // (larger padding + trigger text) for marketing / spacious layouts.
    'size' => config('wirekit.components.accordion.size', 'md'),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Accordion container — visually a vertically stacked list with dividers.
    // `mode` controls whether multiple panels can be open at once:
    //   - 'single'   → opening one closes the others (like radio buttons)
    //   - 'multiple' → any combination of panels can be open (like checkboxes)
    // The mode is exposed to Alpine via data-wk-accordion-mode so that
    // the accordion.item sub-component can read it at click-time.
    //
    // Container classes are variant-driven. `bordered` keeps the original card
    // look; `flush` strips the chrome to just row dividers; `separated` turns
    // the container into a gapped stack and lets each item own its card chrome.
    $variant = in_array($variant, ['bordered', 'flush', 'separated'], true) ? $variant : 'bordered';
    $containerClasses = match ($variant) {
        'flush' => implode(' ', [
            'divide-y-[length:var(--border-wk-width)]',
            'divide-[var(--color-wk-border)]',
        ]),
        'separated' => implode(' ', [
            'flex flex-col',
            'gap-[var(--padding-wk-y-sm)]',
        ]),
        default => implode(' ', [
            'border-[length:var(--border-wk-width)]',
            'border-[var(--color-wk-border)]',
            'rounded-[var(--radius-wk-lg)]',
            'divide-y-[length:var(--border-wk-width)]',
            'divide-[var(--color-wk-border)]',
            'overflow-hidden',
            'bg-[var(--color-wk-bg-elevated)]',
        ]),
    };
    $classes = WireKit::resolveClasses('accordion', 'base', $containerClasses, $scope);
@endphp

{{-- Accordion root — holds the mode flag and exposes a tiny Alpine API:
     `opened` is an array of currently open item ids. Child items access
     toggle()/isOpen() directly via Alpine's scope chain inheritance. --}}
<div
    x-data="{
        mode: @js($mode),
        opened: [],
        toggle(id) {
            // In single mode: either open only this id, or close all if already open.
            // In multiple mode: flip membership in the opened array.
            if (this.mode === 'single') {
                this.opened = this.opened.includes(id) ? [] : [id];
            } else {
                this.opened = this.opened.includes(id)
                    ? this.opened.filter(x => x !== id)
                    : [...this.opened, id];
            }
        },
        isOpen(id) { return this.opened.includes(id); }
    }"
    data-wk-accordion-mode="{{ $mode }}"
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</div>
