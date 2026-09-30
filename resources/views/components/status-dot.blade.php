{{-- optimistic-ui: n/a — client-only
     The one interaction is the tooltip's, which opens on hover and on focus and reaches no server,
     so there is no result that could be shown early. --}}
@props([
    // The state the dot stands for, in the intents a badge takes.
    'intent' => 'neutral',
    // What the dot means, in words: its accessible name, and the text of its tooltip. Without a
    // label the dot is decorative and hidden from assistive technology, so the meaning has to be
    // said somewhere else on the page.
    'label' => null,
    // Show the label in a tooltip on hover and on keyboard focus. Only a labeled dot has one.
    'tooltip' => true,
    // Where the tooltip opens. Above by default: beside a number in a cell aligned to the end, a
    // panel opening to the side lands on the number it explains.
    'placement' => 'top',
    // Whether the dot takes a tab stop of its own, so a keyboard reaches its tooltip. Set false
    // inside a link or a button: the control is the tab stop there, and the dot's name is part of
    // the control's name.
    'focusable' => true,
    // `sm` 6px, `md` 8px, `lg` 10px.
    'size' => 'md',
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy, so `tooltip="false"`
    // would otherwise keep the tooltip. Normalized against each prop's own default.
    $tooltip = BooleanProp::from($tooltip, true);
    $focusable = BooleanProp::from($focusable, true);

    // Dev-only: flags an unknown prop in debug, silent in production.
    WireKit::warnUnknownProps('status-dot', $attributes->getAttributes());

    $intent = WireKit::validateProp('status-dot', 'intent', (string) $intent, ['primary', 'accent', 'success', 'warning', 'danger', 'info', 'neutral']);
    $size = WireKit::validateProp('status-dot', 'size', (string) $size, ['sm', 'md', 'lg']);

    // The dot is a graphical object that carries information, so it needs 3:1 against the page
    // (WCAG 1.4.11). The fill of `warning` stays under that on a light page, so every intent takes
    // its color for text, the one a theme tunes to read on the page. They are the colors the
    // status tiles give their icons.
    $colorClass = match ($intent) {
        'primary', 'accent' => 'bg-[var(--color-wk-accent-text)]',
        'info' => 'bg-[var(--color-wk-info-text)]',
        'success' => 'bg-[var(--color-wk-success-text)]',
        'warning' => 'bg-[var(--color-wk-warning-text)]',
        'danger' => 'bg-[var(--color-wk-danger-text)]',
        default => 'bg-[var(--color-wk-text-muted)]',
    };

    $sizeClass = match ($size) {
        'sm' => 'w-1.5 h-1.5',
        'lg' => 'w-2.5 h-2.5',
        default => 'w-2 h-2',
    };

    // `wk-status-dot` is what the stylesheet's forced-colors rule selects. It is written beside
    // this block rather than in it, so a string personalization of the block cannot take it away.
    $classes = WireKit::resolveClasses('status-dot', 'dot', implode(' ', [
        'inline-block align-middle shrink-0 rounded-full',
        $sizeClass,
        $colorClass,
    ]), $scope);

    // A name the caller writes replaces the label as the name, and a role the caller writes
    // replaces `img`. A dot with neither a label nor a name of the caller's is decorative.
    $callerNamed = $attributes->has('aria-label') || $attributes->has('aria-labelledby');
    $named = filled($label) || $callerNamed;
    $withTooltip = $tooltip && filled($label);

    // A decorative dot manages its own `aria-hidden`, so a caller's copy leaves the bag rather
    // than standing in the tag twice.
    if (! $named) {
        $attributes = $attributes->except('aria-hidden');
    }

    // With a tooltip, the dot sits inside the tooltip's Alpine root. A caller's `x-ref` would be
    // filed there, out of reach of the caller's `$refs`, so it moves to `x-wk-ref` and the
    // tooltip's root marks the boundary (resources/js/utils/caller-ref.js). A caller's
    // `wire:key`, `x-show` and their companions are about the whole component and go on the
    // outermost element (Support\OuterAttributes). Without a tooltip the dot is outermost and
    // keeps all of them.
    $outerAttributes = new \Illuminate\View\ComponentAttributeBag([]);

    if ($withTooltip) {
        $callerRef = trim((string) $attributes->get('x-ref', ''));
        [$outerAttributes, $attributes] = \Pushery\WireKit\Support\OuterAttributes::split($attributes);

        if ($callerRef !== '') {
            $attributes = $attributes->except('x-ref')->merge(['x-wk-ref' => $callerRef]);
            $outerAttributes = $outerAttributes->merge(['data-wk-ref-scope' => true]);
        }
    }
@endphp

{{-- The tooltip describes nothing when its text is the dot's own name, which a screen reader
     would otherwise read twice. With a name of the caller's, the label is extra text and does
     describe the dot. The Blade tags cannot be split across a condition, so the dot is written
     twice. --}}
@if($withTooltip)
    <x-wirekit::tooltip :text="$label" :placement="$placement" as="span" :focusable-trigger="$focusable" :describes="$callerNamed" :attributes="$outerAttributes">
        <span
            data-wk-status-dot
            data-intent="{{ $intent }}"
            @unless($attributes->has('role')) role="img" @endunless
            @unless($callerNamed) aria-label="{{ $label }}" @endunless
            {{ $attributes->class(['wk-status-dot', $classes]) }}
        ></span>
    </x-wirekit::tooltip>
@else
    <span
        data-wk-status-dot
        data-intent="{{ $intent }}"
        @if($named)
            @unless($attributes->has('role')) role="img" @endunless
            @if(! $callerNamed) aria-label="{{ $label }}" @endif
        @else
            aria-hidden="true"
        @endif
        {{ $attributes->class(['wk-status-dot', $classes]) }}
    ></span>
@endif