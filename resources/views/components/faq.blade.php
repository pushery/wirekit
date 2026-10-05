{{-- optimistic-ui: n/a — client-only
     An accordion of questions. Its state is which answers are open, and that is not a value
     a server owns. --}}
@props([
    // Accessible name for the question list.
    'label' => config('wirekit.components.faq.label') ?? __('wirekit::Frequently asked questions'),
    // Visual treatment, passed through to the underlying accordion. 'flush' is
    // the default here (not 'bordered'): an FAQ almost always sits inline in
    // page content, where outer chrome only competes with the section around it.
    'variant' => config('wirekit.components.faq.variant', 'flush'),
    'size' => config('wirekit.components.faq.size', 'lg'),
    // Let several answers stand open at once. Default is one at a time, which is
    // how people read an FAQ: they are hunting one answer, not comparing.
    //
    // A boolean rather than the accordion's `mode` string on purpose. `mode` is a
    // forbidden prop name for new components — the house convention froze the
    // shared-axis names, and the handful of components still on `mode` are
    // grandfathered pending a migration. Widening that list for a brand-new
    // component would be the wrong side of a rule that exists to stop exactly
    // this drift. A bare boolean attribute also reads better at the call site
    // than a string enum with two values.
    'multiple' => false,
    // Emit FAQPage JSON-LD for the questions rendered here.
    //
    // Turn it OFF when the same questions already appear elsewhere on the page,
    // or when this FAQ is one of several: a page must carry exactly one FAQPage,
    // and two of them compete rather than combine.
    'schema' => true,
    // Strip markup from the SCHEMA answer text (the visible answer is untouched).
    // The schema is read outside the page, where the data-* attributes, Alpine
    // directives and utility classes of nested components mean nothing, and plain
    // text is what every reader of it takes reliably. The schema text stays DERIVED
    // from what is rendered — same content, markup removed — so it cannot drift
    // from the page.
    'plainText' => false,
    // Passed to the accordion: a question's answer stays findable by the browser's find in page.
    'findable' => config('wirekit.components.accordion.findable', true),
    // Passed to the accordion: whether an answer animates its height open and shut.
    'animate' => config('wirekit.components.accordion.animate', true),
    'scope' => null,
])

@php
    use Pushery\WireKit\Schema\Schema;
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\Support\FaqCollector;
    use Pushery\WireKit\WireKit;

    // `findable="false"` on an unbound tag is the string "false", which is truthy.
    $findable = BooleanProp::from($findable, config('wirekit.components.accordion.findable', true));

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('faq', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `schema="false"` would switch the JSON-LD ON. That is the exact spelling a
    // developer reaches for when a page carries a second FAQ and must not emit two
    // competing FAQPage nodes, and the page renders normally either way, so
    // nothing would surface the mistake. Both spellings agree.
    $schema = BooleanProp::from($schema, true);
    $multiple = BooleanProp::from($multiple);
    $plainText = BooleanProp::from($plainText);

    // The children have already rendered by the time this body runs, so every
    // faq-item has pushed itself. Draining here — rather than reading — is what
    // keeps two FAQs on one page from bleeding into each other's schema.
    $questions = FaqCollector::drain();

    // Their items still pushed even with schema off, so the buffer must be
    // cleared either way; otherwise those questions would surface in the NEXT
    // FAQ's JSON-LD, describing answers that live somewhere else entirely.
    $emitSchema = $schema && $questions !== [];

    $classes = WireKit::resolveClasses('faq', 'base', '', $scope);
@endphp

{{-- The questions ARE the accordion — this component adds the one thing an
     accordion cannot know: that these rows are questions, and that search
     engines should be told so. --}}
@php
    // A caller's `aria-labelledby` or `aria-label` names the element a reader meets, below; on this
    // wrapper, which has no role, ARIA prohibits a name (Support\CallerName).
    [$callerLabelledBy, $callerLabel, $attributes] = \Pushery\WireKit\Support\CallerName::split($attributes);
@endphp
<div data-wk-faq {{ $attributes->class([$classes]) }}>
    <x-wirekit::accordion
        :variant="$variant"
        :size="$size"
        :mode="$multiple ? 'multiple' : 'single'"
        :findable="$findable"
        {{-- `animate` is not forwarded here. `accordion.item` reads it through `@aware`,
             which walks the ancestor components until it finds the name — and this component
             is one of them, so the value arrives whether the accordion repeats it or not —
             and normalizes it itself, so an unbound `animate="false"` is false there. --}}
        :aria-label="$callerLabelledBy === null ? ($callerLabel ?? $label) : null"
        :aria-labelledby="$callerLabelledBy"
    >
        {{ $slot }}
    </x-wirekit::accordion>

    @if($emitSchema)
        {{-- Built from the questions that just rendered above, never from a
             second hand-written list. That is the whole point: a question can be
             reworded or removed and the schema follows, because it is made out
             of the visible answer rather than a copy of it. --}}
        <x-wirekit::structured-data :data="Schema::faqPage($questions)" />
    @endif
</div>
