{{-- optimistic-ui: n/a — sub-component
     Not a component but an @include shared by `combobox` and `multi-select`. It lays out one
     option row's medium, label and description, and accepts nothing a server could refuse. --}}
{{-- THE INSIDE OF AN OPTION ROW WHEN THE LIST USES MEDIA OR DESCRIPTIONS. A list that uses
     neither never includes this, and keeps the single `x-text` row it always had.

     The label and the description each carry an id, and the row points at them with
     `aria-labelledby` and `aria-describedby`. Without that the option's name would be computed
     from its content, which is the label AND the description run together, so a screen reader
     would announce "Starred Only projects you marked" as one name.

     Params: $idExpression (the JS expression of the row's id), $media, $descriptions (whether the
     list uses them), $mediaBox, $mediaIcon, $mediaInitials (sizes), $descriptionClasses. --}}
@if($media)
    @include('wirekit::components.partials.listbox-option-media', [
        'option' => 'opt',
        'boxClasses' => $mediaBox,
        'iconClasses' => $mediaIcon,
        'initialsClasses' => $mediaInitials,
    ])
@endif
<span class="block min-w-0 flex-1">
    @if($descriptions)
        <span class="block" x-bind:id="{{ $idExpression }} + '-label'" x-text="opt.label"></span>
        <template x-if="opt.description">
            <span class="block {{ $descriptionClasses }}" x-bind:id="{{ $idExpression }} + '-desc'" x-text="opt.description"></span>
        </template>
    @else
        <span class="block" x-text="opt.label"></span>
    @endif
</span>
