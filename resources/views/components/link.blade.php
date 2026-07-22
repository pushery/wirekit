@props([
    'href' => null,
    'variant' => 'default',
    'external' => false,
    'underline' => 'always',
    'as' => 'a',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $variantClasses = match ($variant) {
        'default' => 'text-[color:var(--color-wk-accent-text)]',
        'subtle' => 'text-[color:var(--color-wk-text-subtle)]',
        'muted' => 'text-[color:var(--color-wk-text-muted)]',
        default => WireKit::validateProp('link', 'variant', $variant, ['default', 'subtle', 'muted']),
    };

    $underlineClasses = match ($underline) {
        'always' => 'underline underline-offset-2',
        'hover' => 'hover:underline underline-offset-2',
        'none' => 'no-underline',
        default => WireKit::validateProp('link', 'underline', $underline, ['always', 'hover', 'none']),
    };

    $classes = WireKit::resolveClasses('link', 'base', implode(' ', [
        'font-[family-name:var(--font-wk-sans)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
        'hover:opacity-80',
        $variantClasses,
        $underlineClasses,
    ]), $scope);

    // Auto-detect new-tab behavior from either the $external prop OR an
    // attribute-passed target="_blank". Both paths converge to the same
    // rel="noopener noreferrer" + SR-hint output.
    //
    // CAREFUL: $attributes->merge(['rel' => ...]) treats rel as a DEFAULT —
    // if the caller passed their own rel (even rel="prev"), theirs wins and
    // our auto-injection would silently fail, re-introducing tabnabbing.
    // To force-override, we remove rel from the bag and render it separately.
    $targetAttr = $attributes->get('target', '');
    $opensNewTab = $external || str_contains($targetAttr, '_blank');
    $relAttr = $attributes->get('rel', '');
    $finalRel = $opensNewTab && ! str_contains($relAttr, 'noopener')
        ? trim($relAttr.' noopener noreferrer')
        : $relAttr;
    $computedRel = $opensNewTab ? $finalRel : ($relAttr ?: null);
@endphp

@php
    // Whitespace control: build the trailing HTML (external icon + SR hint) as
    // a single string so the rendered <a> has no whitespace between $slot and
    // </a>. Newlines inside the tag would render as a trailing space and
    // extend the underline past the link text.
    $extLink = $external
        ? '<svg class="inline-block h-3.5 w-3.5 ml-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>'
        : '';
    $newTabHint = $opensNewTab ? '<span class="sr-only">'.e(__('(opens in new tab)')).'</span>' : '';
@endphp

<{{ $as }}
    @if($href) href="{{ $href }}" @endif
    @if($external) target="_blank" @endif
    @if($computedRel) rel="{{ $computedRel }}" @endif
    {{ $attributes->except('rel')->class([$classes]) }}
>{{ $slot }}{!! $extLink !!}{!! $newTabHint !!}</{{ $as }}>