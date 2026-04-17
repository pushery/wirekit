@props([
    'name' => null,
])

@php
    use Pushery\WireKit\Icons\IconResolver;

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

    $mergedAttributes = $attributes->class(['h-5 w-5']);
    if ($shouldSetHidden) {
        $mergedAttributes = $mergedAttributes->merge(['aria-hidden' => 'true']);
    }
@endphp

{{-- Render the SVG icon via blade-icons, passing all merged attributes through --}}
{{ svg($resolved, $mergedAttributes->getAttributes()) }}
