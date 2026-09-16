{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    // 'horizontal' (default) joins children left-to-right; 'vertical' stacks them.
    // Literal default (a sub-component, so it does not dotted-read its own config
    // — mirrors avatar.group / accordion.item).
    'orientation' => 'horizontal',
    // sm | md | lg | xl | 2xl — below that width the group stacks: it joins on the block axis
    // instead of the inline one, so a row whose labels fit on a desktop does not run off a phone.
    // Only meaningful on a horizontal group; a vertical one already stacks at every width.
    'stackBelow' => null,
    // Accessible name for the group (e.g. "View mode"). Recommended when the
    // group acts as a single control (segmented toggle, pagination cluster).
    'label' => null,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('button.group', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    $orientation = $orientation === 'vertical' ? 'vertical' : 'horizontal';

    // `stackBelow` names the width below which the group becomes a column. Both shapes live in
    // `.wk-button-group[data-stack-below="…"]` in dist/wirekit.css and are declared per breakpoint:
    // joining squares the seam edges, and a squared corner cannot be restored from a media query
    // without knowing which radius token the child was drawn with. An unknown value throws in debug
    // and stacks nothing in production; a vertical group drops it, because it already stacks at
    // every width and the two shapes would otherwise be declared over each other.
    $stackBelow = filled($stackBelow) ? (string) $stackBelow : null;

    if ($stackBelow !== null && ! in_array($stackBelow, ['sm', 'md', 'lg', 'xl', '2xl'], true)) {
        WireKit::validateProp('button.group', 'stackBelow', $stackBelow, ['sm', 'md', 'lg', 'xl', '2xl']);
        $stackBelow = null;
    }

    if ($orientation === 'vertical') {
        $stackBelow = null;
    }

    // Joining (squared inner radii + collapsed 1px seam + z-index lift on the
    // active child) lives in the .wk-button-group CSS class (dist/wirekit.css),
    // driven by the data-orientation attribute. RTL-safe (logical properties).
    $classes = WireKit::resolveClasses('button.group', 'base', 'wk-button-group', $scope);
@endphp

<div
    role="group"
    @if($label) aria-label="{{ $label }}" @endif
    data-orientation="{{ $orientation }}"
    @if($stackBelow) data-stack-below="{{ $stackBelow }}" @endif
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</div>
