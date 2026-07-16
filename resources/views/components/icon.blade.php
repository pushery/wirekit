@props([
    'name' => null,
    'size' => null,
])

@php
    use Pushery\WireKit\Icons\IconResolver;
    use Pushery\WireKit\WireKit;

    // Resolve the semantic alias to the actual Blade Icon identifier
    $resolved = app(IconResolver::class)->resolve($name);

    // Check if blade-icons is installed (provides the svg() helper function).
    // Graceful degradation: if the package is missing, render an inert SVG
    // placeholder + emit a one-time framework log entry so the rest of the
    // page still loads. Throwing a hard RuntimeException would kill any
    // page that uses an icon — including pages that use icons transitively
    // through other WireKit components (button close-icons, dropdown
    // chevrons, modal close buttons). The placeholder keeps the layout
    // intact and the dev sees the warning in storage/logs/laravel.log.
    if (! function_exists('svg')) {
        if (! defined('WIREKIT_BLADE_ICONS_WARNED')) {
            define('WIREKIT_BLADE_ICONS_WARNED', true);
            try {
                logger()->warning(
                    '[WireKit] <x-wirekit::icon> requires blade-ui-kit/blade-icons. '.
                    'Run: composer require blade-ui-kit/blade-icons blade-ui-kit/blade-heroicons. '.
                    'Rendering an empty placeholder until installed.'
                );
            } catch (\Throwable $e) {
                // Logger may not be bootstrapped (e.g. early console). Silent fallback.
            }
        }
    }

    // Size map. null preserves the historical h-5 w-5 default — non-null
    // replaces it so the caller's size choice wins over the package default.
    $sizeMap = [
        'xs' => 'h-3 w-3',
        'sm' => 'h-4 w-4',
        'md' => 'h-5 w-5',
        'lg' => 'h-6 w-6',
        'xl' => 'h-8 w-8',
    ];

    if ($size === null) {
        $sizeClasses = 'h-5 w-5';
    } else {
        // validateProp throws in debug, returns the first allowed value ('xs') in production.
        $validated = isset($sizeMap[$size])
            ? $size
            : WireKit::validateProp('icon', 'size', $size, array_keys($sizeMap));
        $sizeClasses = $sizeMap[$validated];
    }

    // A11y default: icons are decorative unless the caller provides an
    // aria-label / aria-labelledby / role="img". In that case we DO NOT
    // add aria-hidden — the caller is declaring it informative.
    // If the caller explicitly sets aria-hidden (true OR false), we respect
    // their choice and never override.
    $callerAriaHidden = $attributes->get('aria-hidden');
    $callerAriaLabel = $attributes->get('aria-label');
    $callerAriaLabelledBy = $attributes->get('aria-labelledby');
    $callerRole = $attributes->get('role');

    $isInformative = $callerAriaLabel || $callerAriaLabelledBy || $callerRole === 'img';
    $shouldSetHidden = $callerAriaHidden === null && ! $isInformative;

    $mergedAttributes = $attributes->class([$sizeClasses]);
    if ($shouldSetHidden) {
        $mergedAttributes = $mergedAttributes->merge(['aria-hidden' => 'true']);
    }
@endphp

{{-- Render the SVG icon via blade-icons, OR a placeholder when blade-icons
     isn't installed yet. The placeholder preserves layout (same h/w as the
     real icon would have) and stays inert (aria-hidden, no glyph).

     Blade-heroicons' raw SVG source files carry `aria-hidden="true"` baked
     into the root element — useful as a sensible default for decorative
     icons, but a screen-reader-skipping a11y bug when the developer marks
     an icon informative via `aria-label` / `aria-labelledby` / `role="img"`.
     We post-process the rendered SVG: strip any source-baked aria-hidden,
     then re-inject our resolved choice (or none, for informative icons).
     This guarantees the rendered SVG carries AT MOST ONE aria-hidden
     attribute, matching the developer's declared intent. --}}
@if (function_exists('svg') && $resolved !== '')
@php
    // BladeUI\Icons\Svg implements Htmlable, not __toString — use toHtml().
    // $resolved is guaranteed non-empty here: an unknown alias in an HTTP
    // request degrades to '' in IconResolver (logged), so we fall through to
    // the inert placeholder below instead of calling svg('') (which would throw).
    $svgHtml = svg($resolved, $mergedAttributes->getAttributes())->toHtml();
    // Strip EVERY aria-hidden attribute from the OUTER <svg ...> open tag.
    // The blade-heroicons source bakes `aria-hidden="true"` into the SVG
    // root; our $mergedAttributes may ALSO have injected one; we leave
    // neither in place and reapply our resolved choice on the line below.
    // Operating on the OPENING TAG ONLY (regex anchored at <svg…>) — never
    // touches nested SVG content (paths, <use>, embedded text labels).
    $svgHtml = preg_replace_callback(
        '/<svg\b([^>]*)>/i',
        function (array $m): string {
            // Drop every aria-hidden="..." occurrence inside the opening
            // tag's attribute list (preserving inter-attribute whitespace
            // collapsed to a single space).
            $cleaned = preg_replace('/\s*aria-hidden="[^"]*"/i', '', $m[1]);
            $cleaned = preg_replace('/\s+/', ' ', (string) $cleaned);

            return '<svg'.$cleaned.'>';
        },
        $svgHtml,
        1
    );
    // Reapply our resolved aria-hidden — only when:
    //   - we decided the icon is decorative (shouldSetHidden=true), OR
    //   - the caller explicitly set aria-hidden (true OR false) — we
    //     respect their literal value, including aria-hidden="false".
    $resolvedAriaHidden = null;
    if ($shouldSetHidden) {
        $resolvedAriaHidden = 'true';
    } elseif ($callerAriaHidden !== null) {
        $resolvedAriaHidden = (string) $callerAriaHidden;
    }
    if ($resolvedAriaHidden !== null) {
        $svgHtml = preg_replace(
            '/<svg\b/',
            '<svg aria-hidden="'.htmlspecialchars($resolvedAriaHidden, ENT_QUOTES).'"',
            $svgHtml,
            1
        );
    }
@endphp
{!! $svgHtml !!}
@else
    <span {{ $mergedAttributes->merge(['aria-hidden' => 'true', 'data-wk-icon-missing' => $name]) }} style="display:inline-block;"></span>
@endif
