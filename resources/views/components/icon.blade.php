@props([
    'name' => null,
    'size' => null,
])

@php
    use Pushery\WireKit\Icons\IconResolver;
    use Pushery\WireKit\WireKit;

    // Resolve the semantic alias to the actual Blade Icon identifier
    $resolved = app(IconResolver::class)->resolve($name);

    // Check if blade-icons is installed (provides the svg() helper function)
    if (! function_exists('svg')) {
        throw new \RuntimeException(
            'WireKit: The icon system requires blade-ui-kit/blade-icons. ' . PHP_EOL
            . 'Run: composer require blade-ui-kit/blade-icons' . PHP_EOL
            . 'Then install your preferred icon set, e.g.: composer require blade-ui-kit/blade-heroicons'
        );
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

{{-- Render the SVG icon via blade-icons, passing all merged attributes through --}}
{{ svg($resolved, $mergedAttributes->getAttributes()) }}
