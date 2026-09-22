{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be shown
     early. The optimistic surface in a cart is the quantity field, which belongs to
     `cart-item`; this component only gives those lines their semantics. --}}
@props([
    // Accessible name for the list. A cart is usually one of several lists on a checkout
    // page, and a `<ul>` with no name is announced as "list, 3 items" beside every other
    // one — the name is what tells them apart.
    'label' => __('wirekit::Cart'),
    // Whether to draw a separator between lines. `divide-y` compiles to a border-BOTTOM on
    // `:not(:last-child)`, so the rule belongs to the list rather than to the line: a line
    // cannot know whether it is the last one, and CSS has no previous-sibling combinator to
    // tell it. `cart-item` therefore ships spacing and no border, and the two must stay that
    // way — a border on both would double.
    'divided' => true,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // House convention: a boolean prop is normalized, because Blade hands `divided="false"`
    // through as the STRING "false", which is truthy — so `divided="false"` on the tag really removes the separators. `BooleanPropCoverageTest` holds it.
    $divided = BooleanProp::from($divided, true);

    // Dev-only — flags unknown props in debug (silent in prod). Declared list auto-derived
    // from this component's @props. Fully qualified: this view's imports may live in a later
    // @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('cart-list', $attributes->getAttributes());

    $classes = WireKit::resolveClasses('cart-list', 'base', implode(' ', [
        // list-none: this is a semantic <ul> for assistive tech, not a bulleted list — a disc
        // marker beside a product thumbnail is pure noise.
        'list-none',
        // The container is NAMED so a line can ask about THIS list's width rather than the
        // viewport's. Measured: in the documentation column a cart line is 369px wide on a
        // 393px phone \u2014 a media query would have called that a phone and been right by
        // accident, and called a 369px sidebar on a desktop a desktop and been wrong.
        '@container/wk-cart-list',
        'flex flex-col',
        $divided ? 'divide-y divide-[color:var(--color-wk-border)]' : '',
    ]), $scope);
@endphp

{{-- role="list" is NOT redundant beside the <ul>. WebKit drops list semantics from a list
whose marker is removed, so a cart styled with `list-none` is announced as a run of text in
Safari and on every iPhone — the engine this repository has an incident about. The explicit
role puts the semantics back.

The inline list-style is a separate belt again: the docs sandbox iframe renders previews
WITHOUT the developer's Tailwind build, so `list-none` is a dead class name there (it DOES
load dist/wirekit.css — that is why the tokens in this component resolve). --}}
<ul data-wk-prose-skip
    role="list"
    aria-label="{{ $label }}"
    data-wk-cart-list
    {{ $attributes->merge(['style' => 'list-style: none; margin: 0; padding: 0;'])->class([$classes]) }}
>
    {{ $slot }}
</ul>
