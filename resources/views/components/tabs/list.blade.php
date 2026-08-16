{{-- optimistic-ui: n/a — navigation
     The bar changes WHICH view the server renders; it does not submit a value the server
     stores. There is nothing to anticipate and nothing to roll back — and the request it
     triggers is the developer's own `wire:click`, which is theirs to make optimistic if
     their action warrants it. --}}
{{-- A tab bar whose selection lives on the SERVER, with no panels underneath.

     `<x-wirekit::tabs>` is the right component when the browser already holds every
     panel's content: it owns the selection in Alpine and shows the matching panel.
     This pair is the same bar for the arrangement a Livewire application reaches for
     first — a row of tabs above server-rendered content, where choosing one is a round
     trip and the page comes back different.

     The difference is not a smaller version of the same thing. There are no panels, so
     there is nothing for `aria-controls` to point at, and the full component's panel
     container plus its dangling references would be markup the developer has to work
     around rather than markup that helps them. And the selection is not this
     component's to hold: the server just rendered it.

     WHY THE FOCUS MODEL READS THE DOM. Livewire REPLACES this markup on every
     selection. An index captured at init would outlive the elements it points at, and
     the failure is silent — focus lands on the wrong tab, or nowhere. So the keyboard
     handlers resolve the tabs at keypress time, every time. Shared with the full
     component, which needs the same property for the same reason.

     WHY ACTIVATION IS MANUAL. The APG allows selection to follow focus, and that is the
     nicer behavior when switching costs nothing. Here it costs a request: arrowing
     across five tabs would fire five round trips and render four pages nobody asked to
     see. Arrows move focus; Enter or Space commits — and that needs no handler, because
     each tab is a real button and the browser already does it. --}}
@props([
    // Names the bar for assistive technology. A tablist with no accessible name is
    // announced as an unlabeled group, which is worse than no landmark at all when a
    // page carries two of them.
    'label' => null,
    'variant' => 'underline',
    'orientation' => 'horizontal',
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\Support\TablistStyles;
    use Pushery\WireKit\WireKit;

    $attributes = BooleanProp::stripFalseHtmlFlags($attributes);

    WireKit::warnUnknownProps('tabs.list', $attributes->getAttributes());

    $isVertical = $orientation === 'vertical';

    if (! in_array($variant, ['underline', 'pills', 'bordered'], true)) {
        WireKit::validateProp('tabs.list', 'variant', $variant, ['underline', 'pills', 'bordered']);
    }

    if (! in_array($orientation, ['horizontal', 'vertical'], true)) {
        WireKit::validateProp('tabs.list', 'orientation', $orientation, ['horizontal', 'vertical']);
    }

    $listClasses = WireKit::resolveClasses('tabs', 'tablist', TablistStyles::list($variant, $isVertical), $scope);
@endphp

<div
    x-data="wirekitTablist()"
    role="tablist"
    @if($label) aria-label="{{ $label }}" @endif
    aria-orientation="{{ $isVertical ? 'vertical' : 'horizontal' }}"
    {{-- Bound on the LIST rather than on each tab. The event originates at a tab and
         bubbles, so one pair of handlers covers every tab including the ones a later
         round trip adds — and a tab that Livewire replaces does not take its own
         handlers with it. --}}
    @if($isVertical)
        x-on:keydown.arrow-down.prevent="moveFocus('next')"
        x-on:keydown.arrow-up.prevent="moveFocus('prev')"
    @else
        x-on:keydown.arrow-right.prevent="moveFocus('next')"
        x-on:keydown.arrow-left.prevent="moveFocus('prev')"
    @endif
    x-on:keydown.home.prevent="moveFocus('first')"
    x-on:keydown.end.prevent="moveFocus('last')"
    {{ $attributes->class([$listClasses]) }}
>
    {{ $slot }}
</div>
