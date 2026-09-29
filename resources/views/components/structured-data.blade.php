{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    'data' => [],

    // A CSP nonce for the JSON-LD block below. Left out, it resolves itself from
    // the container binding or Vite — see WireKit::cspNonce(). Pass one explicitly
    // when the application mints a value per response and publishes it nowhere.
    //
    // A JSON-LD block is a data block: the HTML standard stops preparing a <script>
    // whose type is not a script type before it consults the Content Security Policy,
    // so `script-src` does not apply to it and no policy can reject it. The attribute
    // is harmless there, and it costs one string when a nonce exists and nothing at
    // all when it does not.
    'nonce' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('structured-data', $attributes->getAttributes());

    $wkNonce = $nonce ?? \Pushery\WireKit\WireKit::cspNonce();

    // The `Schema` builders return bare `@type` fragments, so nesting an Offer
    // inside a Product needs no repeated context to strip. The context belongs
    // exactly once, at the top — so add it here when it is missing. This applies
    // to BOTH shapes of a top-level schema.org document: a single node (`@type`)
    // AND a `@graph` composition of several nodes — a bare `@graph`
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

    // Invalid UTF-8, such as text from a legacy Latin-1 column, becomes U+FFFD and the block stays
    // valid. What cannot be JSON at all (NAN, INF, nesting past the depth limit) renders no tag,
    // since an empty ld+json block is invalid structured data, and debug mode says why.
    $json = json_encode($payload, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);

    if ($json === false && config('app.debug')) {
        logger()->warning('WireKit [structured-data]: the data could not be encoded as JSON ('.json_last_error_msg().'), so no JSON-LD block was rendered.');
    }
@endphp

{{--
    Structured Data — emits a <script type="application/ld+json"> block.

    Solves a Blade footgun: @json splits its argument at every comma into the
    value, the flags and the depth. An array literal written into it compiles
    with the flags lost once it holds a comma (`<` then reaches the page
    unescaped) and fails to compile at three. This component takes a PHP array
    and serializes it itself.

    Encoding failures:
      Invalid UTF-8 is replaced with U+FFFD (JSON_INVALID_UTF8_SUBSTITUTE).
      A value JSON cannot hold (NAN, INF, nesting past the depth limit)
      renders no tag at all rather than an empty one, and logs a warning in
      debug mode.

    Security:
      JSON_HEX_TAG encodes `<` and `>` as \u003C / \u003E so a value
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
@if($json !== false)
<script type="application/ld+json"@if($wkNonce) nonce="{{ $wkNonce }}"@endif>
{!! $json !!}
</script>
@endif
