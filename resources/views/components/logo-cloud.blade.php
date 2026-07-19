@props([
    // Optional lead-in ("Trusted by teams at"). Rendered as real text above the
    // wall, so the claim is readable rather than implied by layout alone.
    'label' => null,
    // Accessible name for the list itself. Falls back to the label.
    'ariaLabel' => null,
    // Drain the color out of the logos so a row of clashing brand colors does not
    // compete with the page's own. They return to full color on hover.
    //
    // The word for that filter is deliberately not written here: Tailwind scans
    // this file including its comments, and a bare utility name in prose makes it
    // emit a class nothing uses. The treatment itself lives in dist/wirekit.css.
    //
    // Purely visual: every logo still carries its own alt text, so nothing about
    // the company's identity depends on seeing the color.
    'muted' => true,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $listLabel = $ariaLabel ?? $label;

    $classes = WireKit::resolveClasses('logo-cloud', 'base', implode(' ', [
        // The marker the dist stylesheet hangs the logo treatment on. Styling the
        // children from here would need an arbitrary variant, which the drift
        // auditor cannot trace back to a source emission.
        $muted ? 'wk-logo-cloud wk-logo-cloud-muted' : 'wk-logo-cloud',
        'list-none',
        'grid items-center justify-items-center',
        'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5',
        'gap-[var(--space-wk-lg,1.5rem)]',
    ]), $scope);
@endphp

{{-- A wall of logos is a list of companies, so it is announced as one: someone
     who cannot see the wall still learns how many names are being claimed.

     The inline list-style is not redundant with list-none: the docs sandbox iframe
     renders previews WITHOUT the developer's Tailwind build, so `list-none` is a dead
     class name there (it DOES load dist/wirekit.css — that is why the tokens in this
     component resolve). --}}
<div data-wk-logo-cloud {{ $attributes->only('class') }}>
    @if($label)
        <p class="mb-[var(--space-wk-md,1rem)] text-center text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">
            {{ $label }}
        </p>
    @endif

    <ul
        role="list"
        @if($listLabel) aria-label="{{ $listLabel }}" @endif
        style="list-style: none; margin: 0; padding: 0;"
        {{ $attributes->except('class')->class([$classes]) }}
    >
        {{ $slot }}
    </ul>
</div>
