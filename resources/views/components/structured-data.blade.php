@props([
    'data' => [],
])

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
{!! json_encode($data, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
</script>
