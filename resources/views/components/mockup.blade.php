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

    $variantValue = in_array($variant, ['browser', 'window', 'code', 'phone', 'tablet'], true)
        ? $variant
        : WireKit::validateProp('mockup', 'variant', $variant, ['browser', 'window', 'code', 'phone', 'tablet']);

    // Which variants wear a chrome bar at the top.
    $hasBar = in_array($variantValue, ['browser', 'window', 'code'], true);

    // The label shown in that bar: an address for a browser, a title/filename
    // for the others.
    $barLabel = $variantValue === 'browser' ? $url : $title;

    $classes = WireKit::resolveClasses('mockup', 'base', 'wk-mockup', $scope);
@endphp

{{-- Frame chrome around a screenshot or a live composition.

     ALL of the chrome — the traffic lights, the address bar, the notch — is
     decorative and aria-hidden. It is a picture frame: the slot content carries
     every bit of the meaning, and a screen-reader user is not told about fake
     window buttons they cannot press.

     Nothing here is interactive: the dots are not close/minimize controls, so
     they are spans, not buttons. Rendering them as buttons would put three
     dead controls in the tab order of every marketing page. --}}
<div data-wk-mockup data-variant="{{ $variantValue }}" {{ $attributes->class([$classes]) }}>
    @if($hasBar)
        <div data-wk-mockup-bar aria-hidden="true">
            <span data-wk-mockup-dots>
                <span data-wk-mockup-dot></span>
                <span data-wk-mockup-dot></span>
                <span data-wk-mockup-dot></span>
            </span>

            @if($barLabel)
                <span data-wk-mockup-label>{{ $barLabel }}</span>
            @endif
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
