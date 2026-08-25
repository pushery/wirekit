{{-- optimistic-ui: n/a — sub-component
     A partial of inline-edit; that component owns the editing state. --}}
{{-- Shared editor area for BOTH paths: the built-in control types and a
     developer-supplied control in the `editor` slot.

     One partial rather than two branches, because the failure mode otherwise is
     silent and one-directional: the slot path quietly loses a wiring detail the
     enum path sets — an aria-describedby, an aria-busy — and nothing renders
     differently enough for anyone to notice. A guard compares the two paths'
     emitted ARIA, and it renders the SLOT fixture to do it, so it cannot end up
     checking itself.

     @param string       $id
     @param string       $control
     @param string       $size
     @param string|null  $describedBy
     @param bool         $hasError
     @param bool         $actions
     @param array        $options
     @param string       $actionClasses
     @param string|int   $rows        textarea height: 'auto' (content-sized) or a row count
     @param mixed|null   $editor      developer-supplied control, or null

     WHY THE CONTROL IS WRAPPED IN `flex-1 min-w-0` RATHER THAN CARRYING IT:
     `w-full` on the control alone reads as "fills the row" and does not — the
     editor opened at 192px where read mode had shown 1144px, so clicking a value
     visibly shrank its own field.

     Putting `flex-1` on the control does not fix it either, and the reason is
     worth stating because the class LOOKS applied when you inspect the input:
     these are full form components, so each renders its own `space-y` wrapper
     around label, control and hint. That WRAPPER is the flex item; the input is
     a grandchild. `flex-grow: 1` on a grandchild grows nothing. Measured
     directly — computed `flex-grow: 1` on the input, and a 192px block parent.

     So the wrapper goes here, where it really is the flex item. `min-w-0` lets
     it shrink below the control's intrinsic width on a narrow row instead of
     pushing the buttons out of the container. --}}

{{-- ONE wrapper for all five paths, not five. It is the flex item the row's
     width has to land on, and every branch below needs exactly the same
     treatment — so a per-branch class is four chances for one of them to drift
     out of step. --}}
<div class="flex-1 min-w-0">
@if($editor !== null)
    {{-- The developer's control. It is compiled INSIDE the parent's x-data, so
         `draft`, `commit()` and `cancel()` resolve through Alpine's scope chain
         with no props needing to be threaded in — the same mechanism the
         dropdown trigger already relies on.

         What cannot be handed in from out here is the ARIA wiring, because
         Blade has no way to add attributes to slot content. So it is applied at
         runtime to whatever carries x-ref="control", which is also why that ref
         is part of the documented contract rather than a convenience. --}}
    {{ $editor }}
@elseif($control === 'textarea')
    {{-- `rows` defaults to `auto`, which is the textarea's own content-sizing mode
         (`.wk-autosize`, which is `field-sizing: content` behind an `@supports` —
         the property is NEWER than the supported baseline). Without it the
         editor opened at the config default of three rows regardless of how much text
         it was replacing: a value that read as four wrapped lines became a
         three-row box the reader had to scroll to see their own text in — measured
         102px of read display collapsing to an 81px editor.

         The library already had this mode, which is why nothing is built here. The
         numeric value still works as a minimum, so a developer who wants a fixed
         height passes `rows="3"` and gets exactly the previous behavior. --}}
    <x-wirekit::textarea
        :id="$id"
        :size="$size"
        :rows="$rows"
        x-ref="control"
        x-model="draft"
        x-on:keydown="onKeydown($event)"
        x-on:blur="onBlur()"
        :aria-label="$ariaLabel ?? null"
        :aria-describedby="$describedBy"
        :aria-invalid="$hasError ? 'true' : null"
        class="w-full"
    />
@elseif($control === 'select')
    <x-wirekit::select
        :id="$id"
        :size="$size"
        x-ref="control"
        x-model="draft"
        x-on:keydown="onKeydown($event)"
        :aria-label="$ariaLabel ?? null"
        :aria-describedby="$describedBy"
        :aria-invalid="$hasError ? 'true' : null"
        class="w-full"
    >
        @foreach($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
        @endforeach
    </x-wirekit::select>
@elseif($control === 'number')
    {{-- The arrow keys belong to the spinbutton. The keydown handler
         deliberately does not claim them — taking ArrowUp here would stop the
         control doing the one thing it exists for. --}}
    <x-wirekit::number-input
        :id="$id"
        :size="$size"
        x-ref="control"
        x-model="draft"
        x-on:keydown="onKeydown($event)"
        x-on:blur="onBlur()"
        :aria-label="$ariaLabel ?? null"
        :aria-describedby="$describedBy"
        :aria-invalid="$hasError ? 'true' : null"
        class="w-full"
    />
@else
    <x-wirekit::input
        :id="$id"
        :size="$size"
        x-ref="control"
        x-model="draft"
        x-on:keydown="onKeydown($event)"
        x-on:blur="onBlur()"
        :aria-label="$ariaLabel ?? null"
        :aria-describedby="$describedBy"
        :aria-invalid="$hasError ? 'true' : null"
        class="w-full"
    />
@endif
</div>

@if($actions)
    {{-- aria-disabled rather than disabled: a disabled button drops out of the
         tab order mid-interaction, so a keyboard user loses their place exactly
         while waiting to find out whether the save worked. --}}
    <button
        type="button"
        x-on:click="commit()"
        :aria-disabled="saving"
        :aria-busy="saving"
        class="{{ $actionClasses }} text-[color:var(--color-wk-success-text)]"
        aria-label="{{ __('Confirm') }}"
    >
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
        </svg>
    </button>

    <button
        type="button"
        x-on:click="cancel()"
        :aria-disabled="saving"
        class="{{ $actionClasses }} text-[color:var(--color-wk-text-muted)]"
        aria-label="{{ __('Cancel') }}"
    >
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
        </svg>
    </button>
@endif
