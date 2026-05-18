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

    // QR Code — generates an SVG QR code server-side via bacon/bacon-qr-code.
    // Supports custom colors, error correction levels, and margin (quiet zone).
    $classes = WireKit::resolveClasses('qr-code', 'base', implode(' ', [
        'inline-block',
    ]), $scope);

    // Resolve the accessible name. Fall back to 'QR code' rather than echoing
    // the raw $value, which is often a URL — leaking it as the screen-reader
    // announcement is rarely useful and can be a privacy concern.
    $resolvedLabel = $accessibleLabel ?: 'QR code';

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
            // text alternative" violation. `focusable="false"` keeps it out
            // of the keyboard tab order on legacy browsers (IE/Edge < 18).
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
        {{ $attributes->class([$classes]) }}
        style="width: {{ (int) $size }}px; height: {{ (int) $size }}px;"
    >
        <div aria-hidden="true" class="flex items-center justify-center w-full h-full bg-[var(--color-wk-bg-muted)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] rounded-[var(--radius-wk-md)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">
            <span>QR Code</span>
        </div>
    </div>
@endif
