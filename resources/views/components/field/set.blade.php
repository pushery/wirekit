{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
{{-- `legend` takes two shapes, and both render as the fieldset's FIRST child:
     the string prop `legend="Permissions"` (escaped), or the named slot
     `x-slot:legend` for rich content (markup, a badge, a help icon). The slot is
     the supported route for a rich caption; `x-wirekit::field.legend` written into
     the DEFAULT slot is NOT — see the comment above the <fieldset> below.

     ⚠️ Written as a Blade comment and with the tag names UNBRACKETED on purpose.
     `compileString` strips Blade comments BEFORE it compiles component tags but
     leaves `@props` alone, so an `<x-…>` spelled inside the @props array — even in
     a `//` comment — is compiled as a real component tag and the view dies on an
     undefined `$component`. Measured here on 2026-09-04. --}}
@props([
    'legend' => null,
    'hint' => null,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('field.set', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // <fieldset> is the WCAG-recommended grouping container for related controls
    // (radio groups, checkbox groups, address blocks). The <legend> is its group
    // label, announced by screen readers before each control in the set.
    //
    // We reset the native fieldset chrome (border / padding / margin) and provide
    // our own spacing. `min-w-0` defeats the fieldset's intrinsic `min-width: min-content`
    // quirk that otherwise prevents it from shrinking inside flex/grid layouts.
    $classes = WireKit::resolveClasses('field.set', 'base', 'min-w-0 border-0 p-0 m-0', $scope);

    // The caption and the group hint resolve through the configuration seam like every
    // other surface in the library. They shipped as literals, and that left a project
    // whose group captions are `sm` with no route at all: the prop's typography was not
    // overridable, and the one overridable caption component (`field.legend`) loses the
    // caption ROLE when it is written into this component's default slot. Overriding a
    // block here is what a project reaches for instead of rebuilding the <fieldset>.
    $legendClasses = WireKit::resolveClasses('field.set', 'legend', 'mb-1 text-[length:var(--text-wk-md)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]', $scope);
    $hintClasses = WireKit::resolveClasses('field.set', 'hint', 'mb-3 text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]', $scope);

    // How a slot is WRITTEN decides its TYPE (see Support\SlotAttributes): the block
    // form yields a ComponentSlot, the one-line form a plain string. Only the object
    // carries markup that is meant to render as markup, so only it is emitted raw —
    // a string caption stays escaped, whether it arrived as the prop or as an inline
    // slot. Widening that to "any legend is raw" would turn caller text into markup.
    $legendIsSlot = $legend instanceof \Illuminate\View\ComponentSlot;

    // `filled()` rather than a bare truthiness test: `legend="0"` is a caption a caller
    // asked for, and the old `@if($legend)` dropped it silently.
    $hasLegend = $legendIsSlot ? trim((string) $legend) !== '' : filled($legend);
@endphp

<fieldset {{ $attributes->class([$classes]) }}>
    {{-- ⚠️ A <legend> IS THE GROUP'S CAPTION ONLY WHILE IT IS THE FIELDSET'S FIRST
         CHILD. One level down it is an ordinary inline box: the <fieldset> then has no
         accessible name, and a screen reader announces nothing before the controls —
         which is the only reason to reach for a <fieldset> at all. Nothing about that
         is visible on screen, because the text still renders exactly where the author
         put it, so it survives every look and every axe pass that scans for contrast
         and labels rather than for ancestry.

         That is what a <x-wirekit::field.legend> written into the DEFAULT slot below
         hits: the spacing <div> demotes it to a grandchild. Rich captions therefore go
         through <x-slot:legend> and land HERE, ahead of that <div>. --}}
    @if($hasLegend)
        @if($legendIsSlot)
            <legend {{ $legend->attributes->class([$legendClasses]) }}>{{ $legend }}</legend>
        @else
            <legend class="{{ $legendClasses }}">{{ $legend }}</legend>
        @endif
    @endif
    @if($hint)
        <p class="{{ $hintClasses }}">{{ $hint }}</p>
    @endif

    @if(config('app.debug') && str_contains((string) $slot, '<legend'))
        @php
            // Gated on debug per the house rule: a developer warning never reaches a
            // production page. Silence was the defect here — the demoted caption looks
            // right, so the only way a developer learns about it is being told.
            logger()->warning('[wirekit] field.set: a <legend> was found in the default slot, where it is a grandchild of the <fieldset> and therefore not the group caption. Move it into <x-slot:legend> (rich content is supported there) or pass the `legend` prop.');
        @endphp
    @endif

    {{-- Grouped controls. The space-y gap keeps the fields evenly spaced. --}}
    <div class="space-y-3">
        {{ $slot }}
    </div>
</fieldset>
