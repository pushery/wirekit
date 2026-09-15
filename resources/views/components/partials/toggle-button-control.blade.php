{{-- optimistic-ui: n/a — sub-component
     Not a component but an @include of `toggle-button`, which owns the state and the optimistic
     wiring. It renders the one button that component is, and accepts nothing a server could refuse. --}}
{{-- THE BUTTON OF `toggle-button`, in a partial for one reason: it is rendered either inside a
     `<x-wirekit::tooltip>` or on its own, and a component tag cannot be opened in one `@if` and
     closed in another. Blade pairs component tags when it compiles, so an opening tag behind an
     `@if` swallowed the button whenever there was no tooltip, and the component rendered nothing.

     Params: $attributes, $slot, $size, $scope, $hasIcon, $activeIntent, $labelsChange, $isPressed,
     $swapsIcons, $onIcon, $offIcon, $icon, $iconSize, $onLabel, $offLabel. --}}
<x-wirekit::button
    type="button"
    intent="neutral"
    surface="outline"
    :size="$size"
    :scope="$scope"
    data-wk-toggle-button
    :data-wk-active-intent="$hasIcon ? $activeIntent : null"
    :aria-pressed="$labelsChange ? null : ($isPressed ? 'true' : 'false')"
    :data-wk-state="$labelsChange ? ($isPressed ? 'on' : 'off') : null"
    {{ $attributes }}
>
    @if($swapsIcons)
        {{-- Both glyphs stay in the DOM, stacked in one cell, so the button never changes width.
             Which one shows follows the button's own state attribute in the stylesheet, so it
             follows every writer of that state, not only the one that rendered it. --}}
        <x-wirekit::swap :active="$isPressed" effect="fade" data-wk-toggle-icon aria-hidden="true">
            <x-slot:on><x-wirekit::icon :name="$onIcon" :size="$iconSize" /></x-slot:on>
            <x-slot:off><x-wirekit::icon :name="$offIcon" :size="$iconSize" /></x-slot:off>
        </x-wirekit::swap>
    @elseif(filled($icon))
        <span data-wk-toggle-icon aria-hidden="true" class="inline-flex"><x-wirekit::icon :name="$icon" :size="$iconSize" /></span>
    @endif
    @if($labelsChange)
        {{-- Both labels are rendered and the inactive one is `display: none`, which takes it out of
             the name as well as out of sight, so the name is always the words on the button. --}}
        <span data-wk-toggle-label-on>{{ $onLabel }}</span>
        <span data-wk-toggle-label-off>{{ $offLabel }}</span>
    @endif
    {{ $slot }}
</x-wirekit::button>
