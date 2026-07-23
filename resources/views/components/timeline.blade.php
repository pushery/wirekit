@props([
    'variant' => 'default', // default | centered | compact
    'before' => false, // show dotted fade-in line above first item
    'after' => false,  // show dotted fade-out line below last item
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $before = BooleanProp::from($before, false);
    $after = BooleanProp::from($after, false);

    // Timeline container — semantic ordered list for chronological events.
    // No margin between items (space-y) — spacing comes from content padding
    // inside each item, so the vertical connector line stays continuous.
    $classes = WireKit::resolveClasses('timeline', 'base', implode(' ', [
        // list-none + m-0 + p-0 strip the browser-default <ol> decimal markers
        // and marker indent. Timeline renders its own visual markers per item
        // (dots and connectors); UA "1. 2. 3." prefixes would clash.
        'list-none m-0 p-0',
        'relative',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);
@endphp

<ol {{ $attributes->class([$classes]) }} data-wk-timeline="{{ $variant }}" style="list-style: none; margin: 0; padding: 0;">
    {{-- Optional "before" continuation line — indicates earlier events exist --}}
    @if($before)
        <li aria-hidden="true" style="display: flex; justify-content: center; width: var(--size-wk-xs, 1.5rem);">
            {{-- Dashed line with fade-in from top --}}
            <div style="width: 0; height: 2rem; border-left: 1px dashed var(--color-wk-border); mask-image: linear-gradient(to bottom, transparent, black); -webkit-mask-image: linear-gradient(to bottom, transparent, black);"></div>
        </li>
    @endif

    {{ $slot }}

    {{-- Optional "after" continuation line — indicates later events exist --}}
    @if($after)
        <li aria-hidden="true" style="display: flex; justify-content: center; width: var(--size-wk-xs, 1.5rem);">
            <div style="width: 0; height: 2rem; border-left: 1px dashed var(--color-wk-border); mask-image: linear-gradient(to top, transparent, black); -webkit-mask-image: linear-gradient(to top, transparent, black);"></div>
        </li>
    @endif
</ol>
