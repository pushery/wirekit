{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    'cite' => null,
    'variant' => 'default',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('blockquote', $attributes->getAttributes());

    $variantClasses = match (WireKit::validateProp('blockquote', 'variant', $variant, ['default', 'accent'])) {
        'default' => 'border-[var(--color-wk-border)]',
        'accent' => 'border-[var(--color-wk-accent)]',
    };

    $classes = WireKit::resolveClasses('blockquote', 'base', implode(' ', [
        // A typed token with no break point, an email address or a URL, breaks rather than running
        // out of the box. Inherited by everything inside; `break-word` leaves every min-content width
        // alone, so a table or a flex row in the content keeps the layout it had.
        '[overflow-wrap:break-word]',
        'border-s-4',
        'ps-[var(--space-wk-md,1rem)]',
        'py-[var(--space-wk-xs,0.25rem)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[color:var(--color-wk-text)]',
        'text-[length:var(--text-wk-md)]',
        'italic',
        $variantClasses,
    ]), $scope);

    // With an attribution the figure is the outermost element, so what a caller writes for the
    // component as a whole (`wire:key`, a show directive and its transition and cloak) goes there;
    // the quotation keeps the other attributes.
    [$outerAttributes, $quoteAttributes] = \Pushery\WireKit\Support\OuterAttributes::split($attributes->except(['class', 'style']));
@endphp

{{-- The attribution stands outside the quotation, as HTML asks of a blockquote, so a quote with
     one is a figure: the quotation, then its caption. The figure is the box, with the border, the
     padding and the caller's class and style, so the quote looks the same either way, and it
     takes what the caller writes for the component as a whole; the quotation keeps the caller's
     other attributes. Each branch opens and closes its own elements,
     so Livewire's block markers stay around whole elements. --}}
@if($cite)
    <figure {{ $outerAttributes }} {{ $attributes->only(['class', 'style'])->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}>
        <blockquote data-wk-prose-skip class="m-0" {{ $quoteAttributes }}>
            {{ $slot }}
        </blockquote>
        <figcaption class="mt-[var(--space-wk-sm,0.5rem)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)] not-italic">
            — {{ $cite }}
        </figcaption>
    </figure>
@else
    <blockquote data-wk-prose-skip {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}>
        {{ $slot }}
    </blockquote>
@endif
