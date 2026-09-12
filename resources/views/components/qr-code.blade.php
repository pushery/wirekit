{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'value' => '',
    'size' => 200,
    'color' => '#000000',
    'background' => '#ffffff',
    'errorCorrection' => 'L',
    'margin' => 4,
    // Accessible name for the QR code. Defaults to a generic "QR code"
    // string; authors should pass a descriptive label like "Scan to install
    // WireKit" via this prop. WCAG 1.1.1 — non-text content needs a
    // text alternative; encourage purpose-describing labels over raw URLs.
    'accessibleLabel' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('qr-code', $attributes->getAttributes());

    // QR Code — generates an SVG QR code server-side via bacon/bacon-qr-code.
    // Supports custom colors, error correction levels, and margin (quiet zone).
    $classes = WireKit::resolveClasses('qr-code', 'base', implode(' ', [
        'inline-block',
    ]), $scope);

    // Resolve the accessible name. Fall back to a generic name rather than
    // echoing the raw $value, which is often a URL — leaking it as the
    // screen-reader announcement is rarely useful and can be a privacy concern.
    //
    // ⚠️ Through the catalog, and this is the one string where it decides the
    // whole experience: a QR code is opaque, so `aria-label` is ALL a screen
    // reader has to work with. Frozen to English it announced "QR code" inside
    // a fully German application, with nothing visible to give it away.
    //
    // ⚠️ A passthrough `aria-label` is READ here and then taken OUT of the bag,
    // and both halves are load-bearing. Both render branches below write
    // `aria-label` before the attribute bag, so a caller's own attribute used
    // to arrive as a SECOND aria-label on the same element — and an HTML parser
    // keeps the first, which is ours. The override looked applied in the markup
    // and changed nothing that a screen reader says. `accessibleLabel` still
    // wins over both; this only decides what happens when a caller reaches for
    // the attribute instead of the prop, which the docs page used to recommend.
    $resolvedLabel = $accessibleLabel ?: ($attributes->get('aria-label') ?: __('wirekit::QR code'));
    $attributes = $attributes->except('aria-label');

    $hasQrLibrary = class_exists('\BaconQrCode\Renderer\ImageRenderer');
    $svgContent = null;

    if ($hasQrLibrary && $value) {
        try {
            // Parse hex color strings into BaconQrCode Rgb color objects.
            $fgHex = ltrim($color, '#');
            $bgHex = ltrim($background, '#');
            $fgColor = new \BaconQrCode\Renderer\Color\Rgb(
                (int) hexdec(substr($fgHex, 0, 2)),
                (int) hexdec(substr($fgHex, 2, 2)),
                (int) hexdec(substr($fgHex, 4, 2)),
            );
            $bgColor = new \BaconQrCode\Renderer\Color\Rgb(
                (int) hexdec(substr($bgHex, 0, 2)),
                (int) hexdec(substr($bgHex, 2, 2)),
                (int) hexdec(substr($bgHex, 4, 2)),
            );

            // Map string error correction level to BaconQrCode enum.
            $ecLevel = match (strtoupper($errorCorrection)) {
                'M' => \BaconQrCode\Common\ErrorCorrectionLevel::M(),
                'Q' => \BaconQrCode\Common\ErrorCorrectionLevel::Q(),
                'H' => \BaconQrCode\Common\ErrorCorrectionLevel::H(),
                default => \BaconQrCode\Common\ErrorCorrectionLevel::L(),
            };

            $fill = \BaconQrCode\Renderer\RendererStyle\Fill::uniformColor($bgColor, $fgColor);

            $style = new \BaconQrCode\Renderer\RendererStyle\RendererStyle(
                (int) $size,
                (int) $margin,
                null,  // default square module shape
                null,  // default square eye shape
                $fill,
            );

            $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                $style,
                new \BaconQrCode\Renderer\Image\SvgImageBackEnd(),
            );

            $writer = new \BaconQrCode\Writer($renderer);
            $svgContent = $writer->writeString($value, 'UTF-8', $ecLevel);

            // Mark the inner <svg> as decorative for assistive tech — the
            // accessible name lives on the wrapper <div role="img"> above
            // it, so the SVG itself should be skipped. Without this,
            // axe-core flags every QR-code SVG as a separate "image without
            // text alternative" violation.
            //
            // `focusable="false"` stays because it is harmless and belongs to the
            // same decorative statement, but its REASON is no longer the one this
            // comment gave: it justified the attribute with browsers 93 versions
            // below the support floor. A justification a reader cannot act on is
            // worse than none — it invites either removing something needed or
            // keeping something for a reason that expired.
            if (str_contains($svgContent, '<svg ') && ! str_contains($svgContent, 'aria-hidden=')) {
                $svgContent = preg_replace(
                    '/<svg\b/u',
                    '<svg aria-hidden="true" focusable="false"',
                    $svgContent,
                    1,
                );
            }
        } catch (\Throwable $e) {
            $svgContent = null;
        }
    }
@endphp

@if($svgContent)
    <div
        role="img"
        aria-label="{{ $resolvedLabel }}"
        {{ $attributes->class([$classes]) }}
    >
        {!! $svgContent !!}
    </div>
@else
    {{-- Fallback placeholder when QR library is not available --}}
    <div
        role="img"
        aria-label="{{ $resolvedLabel }}"
        {{ $attributes->merge(['style' => 'width: '.((int) $size).'px; height: '.((int) $size).'px;'])->class([$classes]) }}
    >
        <div aria-hidden="true" class="flex items-center justify-center w-full h-full bg-[var(--color-wk-bg-muted)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] rounded-[var(--radius-wk-md)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">
            <span>{{ __('wirekit::QR code') }}</span>
        </div>
    </div>
@endif
