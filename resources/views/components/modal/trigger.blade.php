{{-- optimistic-ui: n/a — client-only
     It opens the modal. The interaction never leaves the page. --}}
@props([
    'scope' => null,
    'for' => null,
    'href' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('modal.trigger', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    $classes = WireKit::resolveClasses('modal.trigger', 'base', '', $scope);

    // `for` names the modal to open and is what makes this trigger independent of where it sits.
    //
    // ⚠️ WITHOUT it the component is byte-identical to what it has always been: a `<div>` calling
    // `show()` on the modal it is nested in. That is the ordinary case, it is the overwhelming
    // majority of callers, and none of them needed to change for this.
    //
    // WITH it the trigger carries its OWN `x-data` and sends the named event instead. The reason is
    // narrower than "some callers prefer it": a trigger that cannot be nested in its modal is
    // outside every `x-data`, and Alpine walks only inside `x-data` trees — so a hand-written
    // `x-on:click` there is not refused, it is NEVER INSTALLED. Even `.prevent` stops applying,
    // which is why the reported symptom was an anchor that plainly navigated rather than a dead
    // one. The shape that forces this is real and common: a `<label>` renders its content inside
    // itself, so a modal cannot be wrapped around a link that sits in a checkbox's sentence.
    $named = filled($for);

    // ⚠️ THE NAMED TRIGGER DELEGATES TO A PRIMITIVE INSTEAD OF EMITTING ITS OWN TAG, and the
    // first version did the opposite — a hand-written `<a>` and `<button>` right here.
    //
    // A control that a caller can hand attributes to owes four things, and each of them is easy
    // to leave out: a link carrying the caller's attribute bag needs `rel` protection, because a
    // caller who sets `target="_blank"` otherwise hands the opened page `window.opener`; a link
    // that opens a new tab owes the reader an sr-only hint; an element that prose styles needs
    // `data-wk-prose-skip` as its FIRST attribute; and a rendered `<button>` needs a cursor.
    //
    // `link` and `button` already carry all four, and they carry them in ONE place for the whole
    // library. Re-deriving them here would have been a fifth copy that drifts — which is the same
    // rule this repository states for previews that hand-roll a primitive.
    //
    // WHICH primitive is not a detail: the position this exists for is a word inside a consent
    // sentence, and a button-styled box in the middle of a sentence is the wrong shape. So an
    // address gives an inline `link`, and no address gives a standalone `button`.
    $delegate = filled($href) ? 'link' : 'button';
@endphp

@if($named)
{{-- Modal trigger, standalone — opens the NAMED modal from wherever it sits.

     `.stop` as well as `.prevent`, and it is not decoration. The position this exists for is
     inside a `<label>`, and a label forwards any click within it to its control — so opening the
     dialog would ALSO tick the checkbox the reader has not agreed to yet. `.prevent` alone does
     not stop that: it cancels the anchor's navigation, not the label's forwarding. --}}
@if($delegate === 'link')
<x-wirekit::link
    :href="$href"
    x-data="wirekitModalTrigger({ name: {{ \Pushery\WireKit\Support\AlpinePayload::string($for) }} })"
    x-on:click.prevent.stop="openNamedOverlay()"
    {{ $attributes->except(['for', 'href'])->class([$classes]) }}
>{{ $slot }}</x-wirekit::link>
@else
<x-wirekit::button
    x-data="wirekitModalTrigger({ name: {{ \Pushery\WireKit\Support\AlpinePayload::string($for) }} })"
    x-on:click.prevent.stop="openNamedOverlay()"
    {{ $attributes->except(['for', 'href'])->class([$classes]) }}
>{{ $slot }}</x-wirekit::button>
@endif
@else
{{-- Modal trigger — opens the parent modal when clicked --}}
<div
    x-on:click="show()"
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</div>
@endif
