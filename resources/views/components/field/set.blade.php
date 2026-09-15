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
{{-- The group error, and why this view has a class behind it.

     A message can belong to the GROUP rather than to one control: "choose at least one
     role", or a rejected entry of an array field, which Laravel files under `roles.1` while
     every checkbox is bound to `roles`. The set renders that message once, and the
     checkboxes, radios and toggles inside point at it.

     They cannot learn its id from this view, because a slot renders BEFORE the view of the
     component that holds it. `Pushery\WireKit\Components\FieldSet` takes `name`, `error` and
     `hint` in its constructor, which runs before the slot, and hands the controls a group
     object through `@aware`. Everything else stays here, and the props stay declared below,
     because that declaration is what every catalog and guard reads. --}}
@props([
    'legend' => null,
    'hint' => null,
    // The error-bag key the group answers for, and every entry below it: a rejected entry of
    // an array field is filed under its index. The fieldset keeps it as its name attribute.
    'name' => null,
    // A message for the group as a whole. Takes precedence over the error bag.
    'error' => null,
    // Render the group error as a polite live region. Precedence: this prop, then the
    // surrounding form's announce-errors, then config('wirekit.a11y.announce_error').
    'announceError' => null,
    'scope' => null,
])

@aware(['announceErrors' => null])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('field.set', $attributes->getAttributes());

    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\Support\FieldGroup;
    use Pushery\WireKit\WireKit;

    // `@aware` does not take its key out of the attribute bag, so the spelling written on the
    // tag would render as a stray HTML attribute. Both spellings, as in field.
    $attributes = $attributes->except(['announceErrors', 'announce-errors']);

    // announce-error precedence: explicit prop > form container (@aware announceErrors) > global config.
    $announceError = BooleanProp::from($announceError ?? $announceErrors ?? config('wirekit.a11y.announce_error', true), true);

    // The group the controls in the slot already answered to. Through the class it arrives as
    // `$wkFieldSet`, and its ids are the ones they point at. A view compiled before the class
    // existed reaches this file anonymously until its cache is cleared: the props then arrive
    // as attributes, the group is built here, and the message still renders, only without the
    // controls pointing at it.
    $group = isset($wkFieldSet) && $wkFieldSet instanceof FieldGroup
        ? $wkFieldSet
        : FieldGroup::open($name, $error, $hint);
    $groupMessage = $group->message($errors ?? null);

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

    // The group error takes the hint's place, the way a field's error replaces that field's
    // hint: one message under the caption, above the controls a reader is about to scan.
    $errorClasses = WireKit::resolveClasses('field.set', 'error', 'mb-3 text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]', $scope);

    // How a slot is WRITTEN decides its TYPE (see Support\SlotAttributes): the block
    // form yields a ComponentSlot, the one-line form a plain string. Only the object
    // carries markup that is meant to render as markup, so only it is emitted raw —
    // a string caption stays escaped, whether it arrived as the prop or as an inline
    // slot. Widening that to "any legend is raw" would turn caller text into markup.
    $legendIsSlot = $legend instanceof \Illuminate\View\ComponentSlot;

    // `filled()` rather than a bare truthiness test: `legend="0"` is a caption a caller
    // asked for, and the old `@if($legend)` dropped it silently.
    $hasLegend = $legendIsSlot ? $legend->hasActualContent() : filled($legend);
@endphp

<fieldset @if($group->key !== null) name="{{ $group->key }}" @endif {{ $attributes->class([$classes]) }}>
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
    @if($groupMessage !== null)
        <p data-wk-prose-skip id="{{ $group->errorId }}" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="{{ $errorClasses }}">{{ $groupMessage }}</p>
    @elseif($group->hint !== null)
        <p data-wk-prose-skip id="{{ $group->hintId }}" class="{{ $hintClasses }}">{{ $group->hint }}</p>
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
