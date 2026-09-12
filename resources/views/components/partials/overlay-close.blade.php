{{-- optimistic-ui: n/a — client-only
     The shared close button for overlays. It closes. --}}
{{-- Shared close button logic for Modal and Drawer.
     Included via @include('wirekit::components.partials.overlay-close', ['component' => 'modal'])
     from modal/close.blade.php and drawer/close.blade.php.
     The $component variable controls the personalization key. --}}

@php
    use Pushery\WireKit\WireKit;

    $classes = WireKit::resolveClasses("{$component}.close", 'base', '', $scope ?? null);
@endphp

@php
    /*
     * A `<div>` here was correct for ONE slot shape and mouse-only for every other.
     *
     * The reasoning was sound as far as it went: `<x-wirekit::button>` in the slot inside a
     * `<button>` is invalid nested markup, and the inner button does carry the keyboard
     * path. But both docs pages invite arbitrary slot content — an icon, a text span, a
     * `<x-wirekit::icon>` — and for all of those this element WAS the control: no role, no
     * tabindex, no key handler, closeable by pointer alone.
     *
     * So the wrapper asks what it is wrapping. The slot is already-rendered HTML, so an
     * interactive descendant is a fact rather than a guess, and its presence is exactly the
     * condition the nested-button objection depends on.
     */
    $overlayCloseSlot = trim((string) $slot);
    $overlayCloseWrapsAControl = (bool) preg_match('/<(?:button|a)\b/i', $overlayCloseSlot);
@endphp

@if($overlayCloseWrapsAControl)
    {{-- The slot brought its own control. It carries the keyboard path, and a `<button>`
         around it would be invalid nesting — so this stays a plain wrapper whose click
         merely bubbles. --}}
    <div
        x-on:click="close()"
        {{ $attributes->class([$classes]) }}
    >
        {{ $slot }}
    </div>
@else
    {{-- Nothing in the slot can be activated, so THIS is the control. A native button gets
         Enter and Space with no key handler, a focus ring from the surrounding styles, and
         the right role — none of which a `<div>` with a click listener has. --}}
    <button
        type="button"
        x-on:click="close()"
        {{-- `cursor-pointer` unconditionally, and only on THIS branch.
             Tailwind v4's preflight sets `cursor: default` on a button, and the resolved
             class list here is empty unless the developer scopes one — so a dismiss
             control holding nothing but an icon rendered with no pointer at all. The
             wrapper branch above does not need it: whatever it wraps is already a control
             and carries its own.

             Same fix and same reason as link.blade.php, where three auth screens shipped
             a button-shaped action whose only remaining affordance was its underline. --}}
        {{ $attributes->class(['cursor-pointer', $classes]) }}
    >
        {{ $slot }}
    </button>
@endif
