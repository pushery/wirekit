{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
{{-- WireKit Liquid Glass Extension — the FIRST thing in the layout <body>.

     ⚠️ This line used to point at the OTHER end of the document, and the docs page, the
     install command's docblock and the CLI reference all spell out why that is wrong. The
     component emits an <svg>; the HTML parser has no "in head" insertion mode for one, so it
     terminates that section and switches to the body — and every metadata tag after it, a
     canonical link, the Open Graph block, a layout's @stack('meta'), is reparented into the
     body, where a crawler does not look. The page still renders, which is why the wrong
     instruction survived here across releases.

     The wrong placement is deliberately not written out as a phrase: a guard scans these
     surfaces for it, and quoting it would trip the guard from the very comment that fixes it.
     Loads glass CSS, JS, and injects SVG filter definitions for Tier 2.

     Wrapped in @once, and that is a correctness requirement rather than tidiness:
     the filter carries an id, so a second render puts a duplicate id in the
     document. That is invalid HTML, and `getElementById` — which is what a CSS
     `url(#…)` reference resolves through — returns the first match, so the extra
     copies are dead weight that only makes the page harder to reason about.

     It became reachable when the documentation page started rendering the
     component per preview (each sandbox render needs its own filter) on a site
     whose layout already included it: four filters, six stylesheet links. @once
     keeps both cases correct — the standalone preview still gets its filter, and
     a layout that already provides one is not duplicated. --}}
@once
@php
    // The nonce, resolved the same way `fonts` and the asset directives resolve it.
    //
    // This component is the only one that emits EXTERNAL assets, and it was the only one
    // with no nonce path at all. Under a `script-src 'strict-dynamic' 'nonce-…'` policy —
    // which is the shape a nonce-based policy takes — a `<script src>` without the nonce is
    // simply not executed, so the whole Tier-2 runtime went missing with nothing in the page
    // to say why. The stylesheet is the same question one severity down: `style-src` with a
    // nonce rejects an unnonced `<link rel="stylesheet">` and the surface renders unstyled.
    $wkGlassNonce = \Pushery\WireKit\WireKit::cspNonce();
@endphp
<link rel="stylesheet"@if($wkGlassNonce) nonce="{{ $wkGlassNonce }}"@endif href="{{ asset('vendor/wirekit/glass/wirekit-glass.css') }}">
<script @if($wkGlassNonce)nonce="{{ $wkGlassNonce }}"@endif src="{{ asset('vendor/wirekit/glass/wirekit-glass.js') }}" defer></script>

<svg xmlns="http://www.w3.org/2000/svg"
     style="position:absolute;width:0;height:0;overflow:hidden"
     aria-hidden="true">
    <defs>
        <filter id="wk-glass-refract"
                x="-10%" y="-10%" width="120%" height="120%"
                color-interpolation-filters="sRGB">
            <feTurbulence type="fractalNoise"
                         baseFrequency="0.015 0.015"
                         numOctaves="1"
                         seed="2"
                         result="noise"/>
            {{-- scale 60, and it was 20 through four earlier attempts.
                 20 displaces measurably and BENDS NOTHING: rendered against the
                 documentation demo's own backdrop — 2px dots on a 28px pitch —
                 the grid inside the surface stays regular. Measured, not judged
                 by eye: mean per-channel delta 4.79 of 765 at scale 20, 10.61 at
                 60, and the difference is the difference between a grid and one
                 that visibly curves.

                 Compared at three values as images, because "visible" is not a
                 number: at 20 the pattern is a regular grid, at 60 the dots are
                 drawn into short arcs and still read as a pattern, at 120 they
                 dissolve into swirls and the pattern is lost. 60 is what the page
                 promises — "watch the dotted pattern bend behind the box".

                 One lead tested and REFUTED, recorded so nobody re-runs it:
                 raising `baseFrequency` was proposed as buying more than scale,
                 on the reasoning that 0.015 is smooth over ~66px and translates
                 neighboring pixels together. It measures WORSE — 0.04 gives 4.28
                 and 0.08 gives 4.31 against 4.79 at the original frequency. The
                 strength lives in scale. --}}
            <feDisplacementMap in="SourceGraphic"
                              in2="noise"
                              scale="60"
                              xChannelSelector="R"
                              yChannelSelector="G"/>
        </filter>
    </defs>
</svg>
@endonce
