@props([
    'data' => [],
])

@php
    // The `Schema` builders return bare `@type` fragments, so nesting an Offer
    // inside a Product needs no repeated context to strip. The context belongs
    // exactly once, at the top — so add it here when it is missing. This applies
    // to BOTH shapes of a top-level schema.org document: a single node (`@type`)
    // AND a `@graph` composition of several nodes (WIRE-192) — a bare `@graph`
    // with no `@context` is invalid JSON-LD.
    //
    // Back-compatible: data that already carries '@context' (every hand-written
    // usage) is passed through untouched. Data with none of '@context' / '@type'
    // / '@graph' is left alone — that is not a schema.org document and stamping a
    // context onto it would be a lie.
    $payload = $data;
    if (is_array($payload)
        && ! array_key_exists('@context', $payload)
        && (array_key_exists('@type', $payload) || array_key_exists('@graph', $payload))) {
        $payload = ['@context' => 'https://schema.org'] + $payload;
    }
@endphp

{{--
    Structured Data — emits a <script type="application/ld+json"> block.

    Solves a Blade footgun: hand-writing JSON-LD with @json([...]) across
    multiple lines breaks the tokenizer at `{`/`}`. This component takes a
    PHP array and serializes it safely.

    Security:
      JSON_HEX_TAG encodes `<` and `>` as < / > so a value
      containing `</script>` cannot break out of the JSON-LD block.
      This is mandatory — without it, user-controlled string values would
      open an XSS vector.

    Output options:
      JSON_UNESCAPED_SLASHES — keeps URLs readable ("https://x" instead
                               of "https:\/\/x"). Safe because HEX_TAG
                               already neutralizes `<`/`>`.
      JSON_UNESCAPED_UNICODE — keeps non-ASCII as native characters.
      JSON_PRETTY_PRINT      — multi-line output for source readability.

    Usage:
      <x-wirekit::structured-data :data="[
          '@context' => 'https://schema.org',
          '@type'    => 'WebSite',
          'url'      => $canonicalUrl,
          'name'     => 'WireKit',
      ]" />
--}}
<script type="application/ld+json">
{!! json_encode($payload, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
</script>
