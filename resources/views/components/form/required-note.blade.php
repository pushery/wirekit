{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('form.required-note', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // What the asterisk of a required field means, said before the first field that carries one
    // (WCAG technique H90). A label draws the asterisk hidden from assistive technology, which
    // hears the field's `aria-required` instead, so the sentence names the symbol in words and
    // shows it in brackets, in the label's color, the brackets hidden with it.
    $classes = WireKit::resolveClasses('form.required-note', 'base', 'text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]', $scope);
    $wkMark = '<span aria-hidden="true">(<span class="text-[color:var(--color-wk-danger-text)]">*</span>)</span>';
    $wkSentence = e((string) __('wirekit::Required fields are marked with an asterisk :mark.', ['mark' => '__WK_REQUIRED_MARK__']));
    $wkSentence = new \Illuminate\Support\HtmlString(str_replace('__WK_REQUIRED_MARK__', $wkMark, $wkSentence));
@endphp

<p data-wk-prose-skip data-wk-required-note {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}>{{ $wkSentence }}</p>
