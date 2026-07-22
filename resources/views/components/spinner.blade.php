@props([
    // Visual size of the spinner glyph.
    'size' => config('wirekit.components.spinner.size', 'md'),
    // Optional semantic color. Default (null) inherits the surrounding text
    // color via currentColor, so a spinner drops into a button / badge / any
    // text context and matches. Set to a semantic intent for a standalone
    // colored indicator.
    'intent' => config('wirekit.components.spinner.intent', null),
    // Screen-reader accessible name announced via the role="status" live region.
    'label' => __('Loading'),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Validate up front so an invalid value resolves to a real allowed value
    // (then mapped below) — NOT the raw fallback string. Glyph dimensions are
    // structural SVG sizes (not themeable tokens) — the same convention every
    // inline WireKit icon uses (h-4 w-4 etc.).
    if (! in_array($size, ['sm', 'md', 'lg', 'xl'], true)) {
        $size = WireKit::validateProp('spinner', 'size', $size, ['sm', 'md', 'lg', 'xl']);
    }
    $sizeClass = match ($size) {
        'sm' => 'h-4 w-4',
        'lg' => 'h-6 w-6',
        'xl' => 'h-8 w-8',
        default => 'h-5 w-5',
    };

    // Color: null/'' inherits currentColor; a semantic intent maps to its token.
    // Full literal class strings (not interpolated) so the Tailwind scanner sees
    // them. info has no own base color, so it borrows the accent token. A spinner
    // is a non-text GRAPHIC (WCAG 1.4.11, 3:1) — its intent color is the semantic
    // FILL token (like success/warning/danger below), NOT the readable accent-text
    // alias links use; accent-text defaults to near-black, which would strip the
    // brand color from an accent spinner (WIRE-238 accent-as-text repoint exempts it).
    $intentColors = [
        'accent' => 'text-[color:var(--color-wk-accent)]',
        'primary' => 'text-[color:var(--color-wk-accent)]',
        'info' => 'text-[color:var(--color-wk-accent)]',
        'success' => 'text-[color:var(--color-wk-success)]',
        'warning' => 'text-[color:var(--color-wk-warning)]',
        'danger' => 'text-[color:var(--color-wk-danger)]',
        'neutral' => 'text-[color:var(--color-wk-text-muted)]',
    ];
    if ($intent === null || $intent === '') {
        $colorClass = '';
    } else {
        $resolvedIntent = isset($intentColors[$intent])
            ? $intent
            : WireKit::validateProp('spinner', 'intent', $intent, array_keys($intentColors));
        $colorClass = $intentColors[$resolvedIntent] ?? '';
    }

    $wrapperClasses = WireKit::resolveClasses('spinner', 'base', implode(' ', array_filter([
        'inline-flex items-center justify-center',
        $colorClass,
    ])), $scope);
@endphp

{{-- role="status" makes this a polite live region; the visually-hidden label is
     its accessible name, so the loading state is announced even though the SVG
     itself is decorative (aria-hidden). The same ring + arc glyph the button
     loading state uses, scaled by size and spun with the Tailwind animate-spin
     utility. --}}
<span role="status" aria-live="polite" {{ $attributes->class([$wrapperClasses]) }}>
    <svg class="animate-spin {{ $sizeClass }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
    </svg>
    <span class="sr-only">{{ $label }}</span>
</span>
