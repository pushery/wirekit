{{-- optimistic-ui: n/a — sub-component
     A tab is one item of `tabs.list` and carries no state of its own; `selected` arrives
     from the server on every render. Whatever the tab triggers belongs to the developer's
     own action, not to this element. --}}
{{-- One tab inside `<x-wirekit::tabs.list>`, selected by the SERVER.

     `selected` is a plain server-side boolean rather than an Alpine binding, and that is
     the whole point of this pair: the page came back from the server already knowing
     which tab is current, so re-deriving it in the browser would be a second answer to a
     settled question — and the two would disagree for one frame after every round trip.

     It drives BOTH `aria-selected` and `tabindex`, because a tablist has exactly one tab
     in the page's tab order. Setting only the first leaves a bar a keyboard reader can
     enter but not read correctly; setting only the second leaves one it can read but has
     to Tab through item by item.

     There is deliberately no `aria-controls`. It names a panel, this arrangement has
     none, and an IDREF pointing at nothing is not a smaller version of pointing at
     something — assistive technology follows it and arrives nowhere. --}}
@props([
    // Whether the SERVER considers this tab current. Not a default and not initial
    // state: it arrives on every render and is the only thing that decides.
    'selected' => false,
    'disabled' => false,
    'icon' => null,
    'badge' => null,
    // The badge's tone, one of the badge's own intents: `danger` for a count of errors in the panel,
    // which in the neutral default reads like a counter. Null keeps the badge's default.
    'badgeIntent' => null,
    'scope' => null,
])

@aware(['variant' => 'underline', 'orientation' => 'horizontal'])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\Support\TablistStyles;
    use Pushery\WireKit\WireKit;

    // `@aware` reads a value from the parent but — unlike `@props` — does NOT remove
    // that key from the attribute bag. Written on the tag as well, it would survive into
    // `{{ $attributes }}` and render as a stray HTML attribute. Blade accepts both
    // spellings, so both are dropped.
    $attributes = $attributes->except(['variant', 'orientation']);

    // Assigned back onto the prop itself rather than into a new name. The coverage
    // guard reads the assignment target, and it is right to: a normalized copy under a
    // different name leaves the raw prop in scope, where the next edit reaches for it.
    $selected = BooleanProp::from($selected, false);
    $disabled = BooleanProp::from($disabled, false);

    // ⚠️ A DISABLED TAB STAYS DISCOVERABLE, AND THAT COSTS THE COMPONENT THE SUPPRESSION.
    //
    // Native `disabled` on a `role="tab"` removes it from the page's tab order AND from the
    // arrow cycle, so a keyboard reader never learns the tab exists — the WAI-ARIA APG names
    // "Tab elements in a Tablist" explicitly among the controls that should REMAIN focusable
    // while disabled, precisely so the reader can find out that it is there and unavailable.
    //
    // But `aria-disabled` suppresses nothing. Native `disabled` means the browser dispatches
    // no click at all, so a caller's `wire:click` cannot fire; swapping the attribute without
    // more would leave a "disabled" tab that still runs its action. `x-on:click.prevent` is
    // not the answer either — it calls `preventDefault`, while Livewire's own listener sits
    // on the same element, where dispatch order at the target is registration order rather
    // than phase order.
    //
    // So the component takes the handlers off instead. That is the half the proposal behind
    // this change left out, and it is the half that keeps `disabled` meaning disabled.
    $tabAttributes = $disabled
        ? $attributes->filter(fn ($value, $key): bool => ! is_string($key)
            || preg_match('/^(?:wire:|x-on:|@|on)(?:click|mousedown|mouseup|keydown|keyup|keypress)\b/i', $key) !== 1)
        : $attributes;

    $attributes = BooleanProp::stripFalseHtmlFlags($attributes);

    WireKit::warnUnknownProps('tabs.tab', $attributes->getAttributes());

    $isVertical = $orientation === 'vertical';

    $tabClasses = WireKit::resolveClasses('tabs', 'tab', TablistStyles::tab($variant, $isVertical), $scope);
    $stateClasses = $selected ? TablistStyles::tabActive($variant) : TablistStyles::tabInactive();
@endphp

<button
    type="button"
    role="tab"
    aria-selected="{{ $selected ? 'true' : 'false' }}"
    {{-- Roving tabindex: the selected tab is the bar's single stop in the page's tab
         order, and the arrow keys reach the rest. --}}
    tabindex="{{ $selected ? '0' : '-1' }}"
    @if($disabled) aria-disabled="true" @endif
    {{ $tabAttributes->class([$tabClasses, $stateClasses]) }}
>
    @if($icon)
        <x-wirekit::icon :name="$icon" class="h-4 w-4 shrink-0" />
    @endif

    <span>{{ $slot }}</span>

    @if($badge !== null && $badge !== '')
        <x-wirekit::badge size="sm" :intent="$badgeIntent">{{ $badge }}</x-wirekit::badge>
    @endif
</button>
