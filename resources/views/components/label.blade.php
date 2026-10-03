{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    'required' => false,
    // An explanation of the field, shown in a tooltip from a question mark beside the label.
    'help' => null,
    // The id of the hidden copy of `help`, which the field's control lists in aria-describedby.
    // Set by the field components; without it the help is the tooltip alone.
    'helpId' => null,
    // The name of the field the help explains, which a click on the question mark reports as
    // `name` in `wirekit:field-help`. Set by the field components.
    'helpField' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('label', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $required = BooleanProp::from($required, false);

    // Label classes: all values reference design tokens — no hardcoded colors or sizes
    $classes = WireKit::resolveClasses('label', 'base', implode(' ', [
        'block',
        'font-[family-name:var(--font-wk-sans)]',
        'tracking-[var(--font-wk-letter-spacing)]',
        'text-[length:var(--text-wk-md)]',
        // A label is medium weight. `--font-wk-body-weight` sat beside it from the token
        // migration on and never took effect: two weights on one element are decided by the
        // stylesheet's order, and `font-medium` sorts last.
        'font-medium',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);
@endphp

@if(filled($help))
    {{-- The label and its help share a row, and the caller's classes go on the row: they place
         the label (`sr-only`, a column width in a horizontal field), and the help belongs in the
         same place. A label that is visually hidden shows no button, see the partial. --}}
    <div data-wk-label-row class="{{ trim('flex items-center gap-[var(--gap-wk-xs)] '.$attributes->get('class', '')) }}">
        <label {{ $attributes->except('class')->class([$classes]) }}>
            {{ $slot }}@if($required)<span class="text-[color:var(--color-wk-danger-text)] ms-0.5" aria-hidden="true">*</span>@endif
        </label>
        @include('wirekit::components.partials.field-help', [
            'helpText' => (string) $help,
            'helpName' => trim(html_entity_decode(strip_tags((string) $slot), ENT_QUOTES | ENT_HTML5)),
            'helpId' => $helpId,
            'helpButton' => ! in_array('sr-only', preg_split('/\s+/', (string) $attributes->get('class', '')) ?: [], true),
            'helpField' => (string) $helpField,
        ])
    </div>
@else
<label {{ $attributes->class([$classes]) }}>
    {{-- Required indicator uses danger-text variable (auto dark mode, no dark: needed).
         Dense, on one line with the slot, and that is not formatting. A newline between
         the slot and this span is HTML whitespace, which collapses to a space and lands on
         top of `ms-0.5`, so the gap would be visibly wider than in the components that
         write it dense. --}}
    {{ $slot }}@if($required)<span class="text-[color:var(--color-wk-danger-text)] ms-0.5" aria-hidden="true">*</span>@endif
</label>
@endif