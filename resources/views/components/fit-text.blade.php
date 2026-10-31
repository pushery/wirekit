{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    // The smallest size the text may shrink to, as a CSS length: `0.75em`, `0.5rem` or `8px`. Null
    // takes the `--text-wk-fit-floor` token. A floor above the text's own size leaves the text at
    // its own size. A floor in `em` or `rem` follows the font size a reader sets in the browser; one
    // in `px` does not.
    'floor' => null,
    // Whether the text may break over several lines. `true` (the default) fits one line where the
    // floor allows it and otherwise takes the fewest lines the floor needs, at the largest size that
    // keeps them. `false` keeps one line, for a price or a figure: below the floor it runs past the
    // box and is never broken.
    'wrap' => true,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\Support\FitText;
    use Pushery\WireKit\Support\StrictnessGate;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('fit-text', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $wrap = BooleanProp::from($wrap, true, 'fit-text', 'wrap');

    // The floor goes into a style attribute, so only a length gets there; anything else is
    // reported, and the token's floor applies.
    $floorWritten = is_scalar($floor) ? trim((string) $floor) : '';
    $fitFloor = FitText::floor($floorWritten);

    if ($floor !== null && $floor !== '' && $fitFloor === '') {
        StrictnessGate::reject('fit-text', 'floor', is_scalar($floor) ? (string) $floor : get_debug_type($floor), 'a CSS length such as 0.75em, 0.5rem or 8px', '');
    }

    $fitAttributes = $attributes->merge(array_filter(['style' => $fitFloor !== '' ? '--wk-fit-floor: '.$fitFloor.';' : null]));
@endphp
{{ FitText::box($slot, $fitAttributes, $wrap) }}