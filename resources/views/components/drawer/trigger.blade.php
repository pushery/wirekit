{{-- optimistic-ui: n/a — client-only
     It opens the drawer. The interaction never leaves the page. --}}
@props([
    'scope' => null,
    'for' => null,
    'href' => null,
    // The look of the button the trigger draws, handed to it as written: a trigger above a list is
    // usually `intent="neutral" surface="outline"`. Null keeps the button's own default. A trigger
    // with `href` is a link, which takes `size` and has no intent or surface.
    'intent' => null,
    'surface' => null,
    'size' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('drawer.trigger', $attributes->getAttributes());

    // The trigger stands on its own Alpine root: a caller's `x-ref` is filed above it.
    $attributes = \Pushery\WireKit\Support\CallerRef::onRoot($attributes);

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables and
    // `autofocus="false"` focuses — the opposite of what the call site says, with no error
    // either way. Strip such flags when their value reads as false, before the bag is printed.
    $attributes = \Pushery\WireKit\Support\BooleanProp::stripFalseHtmlFlags($attributes);

    // A caller's listener for an event this view listens to on the element the bag lands on
    // goes in the other spelling, so both run (Support\CallerListeners).
    $attributes = \Pushery\WireKit\Support\CallerListeners::beside($attributes, ['x-on:click']);

    use Pushery\WireKit\WireKit;

    $classes = WireKit::resolveClasses('drawer.trigger', 'base', '', $scope);

    // `for` names the drawer to open, and the trigger has no other way to find it. A drawer shows
    // nothing where it is written except its teleported panel, so a trigger cannot be nested in it
    // the way a `modal.trigger` can be nested in its modal. It sits in a toolbar, a navbar or above
    // a list, carries its own `x-data` and sends the drawer's event by name. Without a name it
    // opens nothing, which strict validation reports.
    if (blank($for)) {
        WireKit::validateProp('drawer.trigger', 'for', '', ['the name of the drawer to open']);
    }

    // The trigger delegates to a primitive instead of emitting its own tag, as `modal.trigger`
    // does: `link` and `button` carry the `rel` protection, the new-tab hint, the prose opt-out
    // and the cursor for a control that takes a caller's attributes, in one place for the whole
    // library. An address gives a `link`, which still reaches its page without JavaScript, and no
    // address gives a `button`.
    // An empty address, one of white space or `false` is none (Support\UrlProp), so it gives a
    // `button` as no address does.
    $delegate = (is_string($href) || $href instanceof \Stringable || $href instanceof \BackedEnum) && \Pushery\WireKit\Support\UrlProp::text($href) !== null ? 'link' : 'button';

    // The look the caller gave, for the primitive that takes it: a button takes all three, a link
    // only its size. Handed on through the bag the primitive reads its props from, and only what was
    // given, so the primitive's own defaults hold for the rest.
    $look = array_filter(
        $delegate === 'link' ? ['size' => $size] : ['intent' => $intent, 'surface' => $surface, 'size' => $size],
        static fn ($value): bool => $value !== null,
    );

    // The id `drawer` gives the dialog of a drawer with this name, for `aria-controls`.
    $dialogId = filled($for) ? 'wk-drawer-dialog-'.$for : '';
@endphp

{{-- Drawer trigger — opens the NAMED drawer from wherever it sits.

     `openNamedOverlay($event)` keeps a link from navigating when the drawer opens and keeps the
     click from reaching handlers on the trigger's ancestors, such as a `<label>` it sits in. A link
     clicked with Ctrl, Cmd, Shift or Alt opens its page the way any link does, and no drawer. --}}
@if($delegate === 'link')
<x-wirekit::link
    :href="$href"
    x-data="wirekitModalTrigger({ name: {{ \Pushery\WireKit\Support\AlpinePayload::string($for) }}, overlay: 'drawer', panel: {{ \Pushery\WireKit\Support\AlpinePayload::string($dialogId) }} })"
    x-bind:aria-haspopup="ariaHaspopup"
    x-bind:aria-expanded="ariaExpanded"
    x-bind:aria-controls="ariaControls"
    x-on:click="openNamedOverlay($event)"
    {{ $attributes->except(['for', 'href'])->merge($look)->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}
>{{ $slot }}</x-wirekit::link>
@else
<x-wirekit::button
    x-data="wirekitModalTrigger({ name: {{ \Pushery\WireKit\Support\AlpinePayload::string($for) }}, overlay: 'drawer', panel: {{ \Pushery\WireKit\Support\AlpinePayload::string($dialogId) }} })"
    x-bind:aria-haspopup="ariaHaspopup"
    x-bind:aria-expanded="ariaExpanded"
    x-bind:aria-controls="ariaControls"
    x-on:click="openNamedOverlay($event)"
    {{ $attributes->except(['for', 'href'])->merge($look)->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}
>{{ $slot }}</x-wirekit::button>
@endif
