{{-- optimistic-ui: n/a — sub-component
     An @include of `overflow-nav`, which owns it. The button calls what the caller wires to it and
     accepts nothing of its own that a server could refuse. --}}
{{-- The button beside an entry of `overflow-nav`, drawn in the rows and again in the menu, so the
     entry keeps its action wherever the width puts it.

     Params: $overflowAction (label, icon, attributes). --}}
<x-wirekit::tooltip :text="$overflowAction['label']" :focusable-trigger="false" :describes="false">
    <x-wirekit::button size="xs" intent="neutral" surface="ghost" icon-only data-wk-overflow-action :attributes="$overflowAction['attributes']">
        <x-slot:iconLeft><x-wirekit::icon :name="$overflowAction['icon']" size="sm" aria-hidden="true" /></x-slot:iconLeft>
        {{ $overflowAction['label'] }}
    </x-wirekit::button>
</x-wirekit::tooltip>
