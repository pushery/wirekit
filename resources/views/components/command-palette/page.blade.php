{{-- optimistic-ui: n/a — presentational
     A list of entries the palette shows in place of its top level. The entries carry the
     developer's actions, and the palette owns which level shows. --}}
@props([
    // The page's name, which an entry's `opens` and the palette's events use.
    'name' => null,
    // The page's name for a reader: the header above the field, the group, and what the status
    // region says when the page opens. The `name` without one.
    'heading' => null,
    // The field's placeholder while the page is open. The palette's own without one.
    'placeholder' => null,
    // The host answers the page's search, as it answers the search of the top level: the query
    // event carries the page's name, and the page shows what the host renders into it. Without
    // it the palette filters the page's entries by their text as the reader types.
    'remote' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('command-palette.page', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    $remote = BooleanProp::from($remote, false, 'command-palette.page', 'remote');

    $name = filled($name) ? trim(\Pushery\WireKit\Support\NumericProp::text($name)) : '';

    // A page without a name cannot be opened: no entry can name it.
    if ($name === '') {
        \Pushery\WireKit\Support\StrictnessGate::reject('command-palette.page', 'name', '', 'a name that an entry\'s `opens` names', '');
    }

    // Both go into attributes the palette's script reads, so markup is read as its text.
    $heading = filled($heading) ? ($heading instanceof \Illuminate\Contracts\Support\Htmlable ? \Pushery\WireKit\Support\SlotContent::textOf($heading) : \Pushery\WireKit\Support\NumericProp::text($heading)) : $name;
    $placeholder = filled($placeholder) ? ($placeholder instanceof \Illuminate\Contracts\Support\Htmlable ? \Pushery\WireKit\Support\SlotContent::textOf($placeholder) : \Pushery\WireKit\Support\NumericProp::text($placeholder)) : null;
@endphp

{{-- A group of the listbox, named by its heading, shown only while it is the open page. `x-cloak`
     keeps it out of the first frame, before Alpine has read which page is open. --}}
<div
    role="group"
    @unless(\Pushery\WireKit\Support\CallerAttribute::writes($attributes, 'aria-label') || \Pushery\WireKit\Support\CallerAttribute::writes($attributes, 'aria-labelledby')) aria-label="{{ \Pushery\WireKit\Support\SlotContent::textOf($heading) }}" @endunless
    data-wk-command-page="{{ $name }}"
    data-wk-command-heading="{{ \Pushery\WireKit\Support\SlotContent::textOf($heading) }}"
    @if($placeholder !== null) data-wk-command-placeholder="{{ \Pushery\WireKit\Support\SlotContent::textOf($placeholder) }}" @endif
    @if($remote) data-wk-command-remote @endif
    x-show="pageShows({{ \Pushery\WireKit\Support\AlpinePayload::string($name) }})"
    x-cloak
    {{ $attributes->except(['role', 'x-show']) }}
>
    {{ $slot }}
</div>
