{{-- optimistic-ui: n/a — navigation
     The anchor primitive. Where a developer puts a wire:click on it, the optimism belongs to whatever that action changes — not to the link. --}}
@props([
    'href' => null,
    'variant' => 'default',
    'external' => false,
    'underline' => 'always',
    'as' => 'a',
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('link', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $external = BooleanProp::from($external, false);

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
        // Unconditional, and NOT narrowed to the non-anchor branch. A link is defined by what
        // it does rather than by the tag it happens to render, and this component renders a
        // `<button>` whenever the action has to be a POST or a Livewire call — which is exactly
        // what `as="button"` is for. On an anchor this repeats what the user-agent stylesheet
        // already says and costs nothing; on a button it replaces the `cursor: default` that
        // Tailwind v4's preflight sets, which is where the pointer went missing. Reported from
        // three auth screens where the only affordance left was the underline.
        'cursor-pointer',
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

    // `as` is interpolated straight into the opening tag, and Blade's escaping does
    // not stop a space or an `=` — so an unvalidated value renders as an attribute.
    $as = \Pushery\WireKit\WireKit::tagName('link', (string) $as);
@endphp

@php
    // Whitespace control: build the trailing HTML (external icon + SR hint) as
    // a single string so the rendered <a> has no whitespace between $slot and
    // </a>. Newlines inside the tag would render as a trailing space and
    // extend the underline past the link text.
    $extLink = $external
        ? '<svg class="inline-block h-3.5 w-3.5 ml-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>'
        : '';
    $newTabHint = $opensNewTab ? '<span class="sr-only">'.e(__('wirekit::(opens in new tab)')).'</span>' : '';
@endphp

<{{ $as }}
    @if($href) href="{{ $href }}" @endif
    @if($external) target="_blank" @endif
    @if($computedRel) rel="{{ $computedRel }}" @endif
    {{ $attributes->except('rel')->class([$classes]) }}
>{{ $slot }}{!! $extLink !!}{!! $newTabHint !!}</{{ $as }}>