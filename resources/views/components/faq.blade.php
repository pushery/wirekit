@props([
    // Accessible name for the question list.
    'label' => 'Frequently asked questions',
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
    'scope' => null,
])

@php
    use Pushery\WireKit\Schema\Schema;
    use Pushery\WireKit\Support\FaqCollector;
    use Pushery\WireKit\WireKit;

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
<div data-wk-faq {{ $attributes->class([$classes]) }}>
    <x-wirekit::accordion
        :variant="$variant"
        :size="$size"
        :mode="$multiple ? 'multiple' : 'single'"
        :aria-label="$label"
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
