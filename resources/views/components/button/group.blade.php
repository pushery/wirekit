{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    // 'horizontal' (default) joins children along the line; 'vertical' stacks them.
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

    // An unknown `orientation` is reported (Support\StrictnessGate) and renders as `horizontal`; a
    // bound `null` or empty string is `horizontal` without a word.
    $orientation = \Pushery\WireKit\WireKit::validateProp('button.group', 'orientation', \Pushery\WireKit\Support\NumericProp::text($orientation ?: 'horizontal'), ['horizontal', 'vertical']);

    use Pushery\WireKit\WireKit;

    // `stackBelow` names the width below which the group becomes a column. Both shapes live in
    // `.wk-button-group[data-stack-below="…"]` in dist/wirekit.css and are declared per breakpoint:
    // joining squares the seam edges, and a squared corner cannot be restored from a media query
    // without knowing which radius token the child was drawn with. An unknown value is reported
    // through the strictness gate and stacks nothing; a vertical group drops it, because it already
    // stacks at every width and the two shapes would otherwise be declared over each other.
    $stackBelow = filled($stackBelow) ? (string) $stackBelow : null;

    if ($stackBelow !== null && ! in_array($stackBelow, ['sm', 'md', 'lg', 'xl', '2xl'], true)) {
        WireKit::validateProp('button.group', 'stackBelow', $stackBelow, ['sm', 'md', 'lg', 'xl', '2xl']);
        $stackBelow = null;
    }

    if ($orientation === 'vertical') {
        $stackBelow = null;
    }

    // The weld (squared inner corners, one seam, the hovered or focused child lifted) is the rule
    // set of `[data-wk-button-group]` in dist/wirekit.css, which `button-group` writes as well, driven
    // by `data-orientation` and `data-stack-below`. The class lays the group out; a personalized base
    // class keeps the weld, since the attribute carries it.
    $classes = WireKit::resolveClasses('button.group', 'base', 'wk-button-group', $scope);
@endphp

<div
    @unless(\Pushery\WireKit\Support\CallerAttribute::writes($attributes, 'role')) role="group" @endunless
    @if($label && ! \Pushery\WireKit\Support\CallerAttribute::writes($attributes, 'aria-label') && ! \Pushery\WireKit\Support\CallerAttribute::writes($attributes, 'aria-labelledby')) aria-label="{{ \Pushery\WireKit\Support\SlotContent::textOf($label) }}" @endif
    data-wk-button-group
    data-orientation="{{ $orientation }}"
    @if($stackBelow) data-stack-below="{{ $stackBelow }}" @endif
    {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}
>
    {{ $slot }}
</div>
