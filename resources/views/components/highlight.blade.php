{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'query' => null,
    'as' => 'span',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('highlight', $attributes->getAttributes());

    $classes = WireKit::resolveClasses('highlight', 'base', implode(' ', [
        'font-[family-name:var(--font-wk-sans)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);

    $markClasses = implode(' ', [
        // No fallback. It read `oklch(0.905_0.093_102.1)`, which matches NEITHER declared
        // value of this token — light is `oklch(96.4% 0.058 102.1)` and dark is
        // `oklch(32% 0.06 80)`. So in the one situation a fallback exists for, the highlight
        // came out a color the theme never chose, in both modes, and the palette guard
        // cannot see a raw color function to say so.
        'bg-[var(--color-wk-warning-bg)]',
        'text-[color:var(--color-wk-text)]',
        'rounded-[var(--radius-wk-sm)]',
        'px-0.5',
    ]);

    // `as` is interpolated straight into the opening tag, and Blade's escaping does
    // not stop a space or an `=` — so an unvalidated value renders as an attribute.
    $as = \Pushery\WireKit\WireKit::tagName('highlight', (string) $as);

    /*
     * The highlighted markup, built here rather than in the template below.
     *
     * The slot arrives as RENDERED HTML: Blade has already escaped whatever text the caller
     * wrote and left whatever markup they wrote intact. The previous implementation split that
     * string on the query and printed each fragment through `{{ }}`, escaping it a SECOND
     * time. Three consequences, and the same component renders all three correctly the moment
     * the query is removed:
     *
     *   markup     `<em>note</em>` came out as visible angle brackets
     *   entities   an `&amp;` the caller had escaped came out as `&amp;amp;`
     *   tags       splitting the HTML on the query alone cut INSIDE a tag — `query="em"`
     *              matched the `em` in `<em>` and wrapped the halves in `<mark>`
     *
     * So the content is split into TAG and TEXT segments first and only the text is searched;
     * tags pass through untouched, and each text segment is decoded, searched and escaped once
     * on the way out, so it leaves as escaped as it arrived.
     *
     * Assembled into one string rather than emitted through a Blade `@foreach`: a loop puts a
     * newline around every iteration, and in inline content a newline IS a space, so
     * `foo<em>bar</em>` came back with gaps the caller never wrote.
     */
    $highlighted = null;

    if ($query) {
        $pattern = '/('.preg_quote((string) $query, '/').')/iu';
        $highlighted = '';

        foreach (preg_split('/(<[^>]*>)/', (string) $slot, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $segment) {
            if ($segment === '' || str_starts_with($segment, '<')) {
                $highlighted .= $segment;

                continue;
            }

            // Each text segment is searched DECODED and every part is escaped again on the way
            // out, so a match never falls inside an entity (`query="amp"` leaves `&amp;` whole)
            // and a query with an `&` in it finds the character the reader sees. `/u` folds
            // case beyond ASCII, so `query="über"` finds "Über".
            $parts = preg_split($pattern, html_entity_decode($segment, ENT_QUOTES | ENT_HTML5, 'UTF-8'), -1, PREG_SPLIT_DELIM_CAPTURE);

            if ($parts === false) {
                // Text that is not valid UTF-8 cannot be searched; it stays as it arrived.
                $highlighted .= $segment;

                continue;
            }

            foreach ($parts as $index => $part) {
                // With one capturing group, every odd part is a match.
                $highlighted .= $index % 2 === 1
                    ? '<mark class="'.e($markClasses).'">'.e($part).'</mark>'
                    : e($part);
            }
        }
    }
@endphp

@if($query)
    <{{ $as }} data-wk-prose-skip {{ $attributes->class([$classes]) }}>{!! $highlighted !!}</{{ $as }}>
@else
    <{{ $as }} data-wk-prose-skip {{ $attributes->class([$classes]) }}>{{ $slot }}</{{ $as }}>
@endif