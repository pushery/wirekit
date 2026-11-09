{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    // browser — URL bar + traffic lights (a web app screenshot)
    // window  — title bar + traffic lights (a desktop app)
    // code    — filename tab + traffic lights (a snippet, pairs with code-block)
    // phone   — a phone frame
    // tablet  — a tablet frame
    'variant' => 'browser',
    // Shown in the browser variant's address bar.
    'url' => null,
    // Shown in the window / code variant's title area.
    'title' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('mockup', $attributes->getAttributes());

    $variantValue = in_array($variant, ['browser', 'window', 'code', 'phone', 'tablet'], true)
        ? $variant
        : WireKit::validateProp('mockup', 'variant', $variant, ['browser', 'window', 'code', 'phone', 'tablet']);

    // Which variants wear a chrome bar at the top.
    $hasBar = in_array($variantValue, ['browser', 'window', 'code'], true);

    // The label shown in that bar: an address for a browser, a title/filename
    // for the others.
    $barLabel = $variantValue === 'browser' ? $url : $title;

    $classes = WireKit::resolveClasses('mockup', 'base', 'wk-mockup', $scope);

    // A width class the caller puts on the frame (`w-*`, `max-w-*`, `size-*`) takes the place of
    // the frame's default width. That default lives in wirekit.css, which is unlayered and so
    // outranks every utility; the attribute is what its rules leave out. `min-w-*` sets no width,
    // and a class under a variant (`md:w-full`) applies at a width of its own, so both leave the
    // default in place.
    $sized = preg_match('/(?:^|\s)(?:w|max-w|size)-\S/', (string) $attributes->get('class', '')) === 1;
@endphp

{{-- Frame chrome around a screenshot or a live composition.

     All of the chrome — the traffic lights, the address bar, the notch — is
     decorative and aria-hidden. It is a picture frame: the slot content carries
     every bit of the meaning, and a screen-reader user is not told about fake
     window buttons they cannot press.

     Nothing here is interactive: the dots are not close/minimize controls, so
     they are spans, not buttons. Rendering them as buttons would put three
     dead controls in the tab order of every marketing page. --}}
<div data-wk-mockup data-variant="{{ $variantValue }}" @if($sized) data-wk-mockup-sized @endif {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}>
    @if($hasBar)
        <div data-wk-mockup-bar aria-hidden="true">
            <span data-wk-mockup-dots>
                <span data-wk-mockup-dot></span>
                <span data-wk-mockup-dot></span>
                <span data-wk-mockup-dot></span>
            </span>

            @wirekitLast($barLabel)
                <span data-wk-mockup-label>{{ $barLabel }}</span>
            @endwirekitLast
        </div>
    @endif

    @if($variantValue === 'phone')
        {{-- The notch is pure decoration; it must not be announced. --}}
        <span data-wk-mockup-notch aria-hidden="true"></span>
    @endif

    <div data-wk-mockup-screen>
        {{ $slot }}
    </div>
</div>
