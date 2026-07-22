{{-- Pick up the surrounding faq's appearance so the items match the container.
     accordion.item resolves its own chrome from `variant` / `size`, and this
     component sits between the two — so without bridging them here, a flush FAQ
     would render bordered rows inside a flush container.

     The fallbacks are faq's OWN defaults, not the accordion's: @aware only ever
     sees what was passed to the parent as an explicit attribute, never the
     parent's @props default (verified — it is a real Laravel limitation, not an
     oversight). So a plain <x-wirekit::faq> with no attributes would otherwise
     silently fall back to the accordion's bordered/md here — which is exactly the
     common case, and exactly how this shipped broken the first time. --}}
@aware([
    'variant' => config('wirekit.components.faq.variant', 'flush'),
    'size' => config('wirekit.components.faq.size', 'lg'),
    // Set on the surrounding faq: strip markup from the SCHEMA answer text.
    'plainText' => false,
])

@props([
    // The question. Rendered as the accordion trigger AND recorded for the
    // FAQPage schema — one string, both jobs, so they cannot disagree.
    'question' => '',
    // Escape hatch for the rare case where the schema answer must differ from the
    // visible one in CONTENT, not just markup. Prefer `plain-text` on the faq: a
    // hand-written duplicate is copy that can drift away from what the reader
    // sees, which is exactly what deriving the schema from the render prevents.
    'schemaAnswer' => null,
    'id' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\FaqCollector;

    // Resolve the slot to a string once: it is both the visible answer and the
    // schema's answer text, and rendering it twice could produce two different
    // strings (anything with a random id, a counter, or a date in it).
    $answerHtml = trim($slot->toHtml());

    // What the schema records. Still DERIVED from what is rendered — `plainText`
    // only removes markup, it never re-authors the answer, so the schema and the
    // page cannot drift apart. Google's FAQPage accepts a limited HTML subset, and
    // arbitrary rendered markup (nested components, data-* attributes, Alpine
    // directives) can fail rich-result validation; stripping to text lands inside
    // the supported subset. Entities are decoded so the schema carries real
    // characters rather than `&amp;`, and whitespace is collapsed because the
    // rendered indentation is layout, not content.
    $schemaText = $schemaAnswer;

    if ($schemaText === null) {
        $schemaText = $plainText
            ? trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($answerHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8')))
            : $answerHtml;
    }

    // Record what is actually being rendered. A search engine is told about this
    // answer precisely because the reader is being shown it.
    FaqCollector::push($question, $schemaText);
@endphp

<x-wirekit::accordion.item
    :title="$question"
    :id="$id"
    :scope="$scope"
    :variant="$variant"
    :size="$size"
    data-wk-faq-item
    {{ $attributes }}
>
    {!! $answerHtml !!}
</x-wirekit::accordion.item>
