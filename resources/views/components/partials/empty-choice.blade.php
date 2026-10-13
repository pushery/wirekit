{{-- optimistic-ui: n/a — sub-component
     A partial of the choice components. It shows a sentence and a link. --}}
{{-- What a choice says when there is nothing to choose: what is missing, and where it is created.
     Included with:

       $emptyFor        the component it stands under, `select` or `combobox`; it names the
                        marker, `data-wk-select-empty` or `data-wk-combobox-empty`
       $emptyId         the id the control names in its aria-describedby
       $emptyText       the sentence
       $emptyHref       where the missing record is created, or null for a reader who may not
       $emptyAction     the link's text, or null for "Create one"
       $emptyNavigates  whether the link moves with `wire:navigate`
       $emptySlot       the caller's `empty` slot, which replaces the sentence and the link, or null
       $emptyClass      classes for where it stands, or null

     It sits outside the control, which is disabled, so the link stays in the tab order, and the
     control names it in aria-describedby, so a screen reader on the field hears why it is empty. --}}
<div data-wk-prose-skip @if($emptyFor === 'combobox') data-wk-combobox-empty @else data-wk-select-empty @endif id="{{ $emptyId }}" class="{{ trim('text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)] '.($emptyClass ?? '')) }}">
    @if($emptySlot !== null)
        {{ $emptySlot }}
    @else
        {{ $emptyText }}
        @if(filled($emptyHref) && $emptyNavigates)
            <x-wirekit::link :href="$emptyHref" wire:navigate>{{ filled($emptyAction) ? $emptyAction : __('wirekit::Create one') }}</x-wirekit::link>
        @elseif(filled($emptyHref))
            <x-wirekit::link :href="$emptyHref">{{ filled($emptyAction) ? $emptyAction : __('wirekit::Create one') }}</x-wirekit::link>
        @endif
    @endif
</div>
