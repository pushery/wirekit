{{-- optimistic-ui: n/a — sub-component
     Not a component but an @include of the datetime picker with `type="input"`; it holds no state
     of its own and binds to the moment input its scope spreads into its factory
     (resources/js/utils/moment-input.js): the picker's own for a single moment, the scope of each
     end for a range (resources/js/components/datetime-picker-moment.js). --}}
{{-- The segments of a moment typed in place.

     The day, the month, the year, the hour, the minute and the period of the day in the locale's
     order with the text between them. Each segment is an `<input>` with the role of a spin button,
     named after what it holds, with its range and its value; an empty one shows a placeholder. The
     script knows the locale's order, so it writes the segments into the frame the server renders.
     Each segment stands on an invisible copy of the widest text it can show, which gives it its
     width. The frame around them, its name and the buttons beside them are the picker's.

     Variables: $momentInputDescribedBy, $momentInputText (the text size each segment takes as a
     utility of its own, which the touch floor of `wk-field` lifts to 16px), $momentInputFirstId
     (the id the first segment takes, which the label points at, or null), $momentInputDisabled,
     $momentInputReadonly, $momentInputRequired and $momentInputServerError. --}}
@php
    $momentInputFirstId = $momentInputFirstId ?? null;
@endphp
{{-- The date and the time are runs of their own: a field too narrow for the whole moment, a phone
     at 320 pixels, puts the time on a line under the date rather than past its own edge, and never
     breaks inside a run. --}}
<span class="flex min-w-0 flex-1 flex-wrap items-center py-[calc(var(--padding-wk-y-xs)/2)] tabular-nums">
    <template x-for="group in momentInputGroups()" :key="group.key">
        <span class="inline-flex items-center">
            <template x-for="part in group.parts" :key="part.key">
                <span class="inline-grid">
                    <span aria-hidden="true" class="invisible col-start-1 row-start-1 whitespace-pre px-[calc(var(--padding-wk-x-xs)/2)]" x-text="part.sizer"></span>
                    <template x-if="! part.segment">
                        <span aria-hidden="true" class="col-start-1 row-start-1 whitespace-pre" x-text="part.text"></span>
                    </template>
                    <template x-if="part.segment">
                        <input
                            type="text"
                            role="spinbutton"
                            x-wk-ime
                            autocomplete="off"
                            spellcheck="false"
                            {{-- The invisible copy above sets the segment's width. A text field is twenty characters
                                 wide on its own, and that width counts where the box is sized to its content, which
                                 `w-full` does not: without `size` a segment is as wide as a whole field. --}}
                            size="1"
                            aria-describedby="{{ $momentInputDescribedBy }}"
                            @if($momentInputDisabled) disabled @endif
                            @if($momentInputReadonly) readonly @endif
                            @if($momentInputRequired) aria-required="true" @endif
                            @if($momentInputServerError) aria-invalid="true" @else :aria-invalid="momentInputInvalid() ? 'true' : null" @endif
                            @if($momentInputFirstId !== null) :id="part.first ? {{ \Pushery\WireKit\Support\AlpinePayload::string($momentInputFirstId) }} : null" @endif
                            :placeholder="part.placeholder"
                            :inputmode="part.inputmode"
                            :data-wk-moment-segment="part.type"
                            :aria-label="part.name"
                            :aria-valuemin="part.min"
                            :aria-valuemax="momentInputMax(part.type)"
                            :aria-valuenow="momentInputValueNow(part.type)"
                            :aria-valuetext="momentInputValueText(part.type)"
                            :value="momentInputText(part.type)"
                            x-on:focus="momentInputFocus(part.type)"
                            x-on:keydown="momentInputKeydown($event, part.type)"
                            x-on:beforeinput="momentInputBeforeInput($event, part.type)"
                            x-on:input="momentInputInput($event, part.type)"
                            x-on:paste="momentInputPaste($event, part.type)"
                            class="wk-field wk-moment-segment {{ $momentInputText }} col-start-1 row-start-1 w-full min-w-0 rounded-[var(--radius-wk-sm)] bg-transparent px-[calc(var(--padding-wk-x-xs)/2)] text-center text-[color:var(--color-wk-text)] caret-transparent focus:outline-hidden placeholder:text-[color:var(--color-wk-text-placeholder)] focus:bg-[var(--color-wk-accent)] focus:text-[color:var(--color-wk-accent-fg)] focus:placeholder:text-[color:var(--color-wk-accent-fg)] disabled:cursor-not-allowed"
                        />
                    </template>
                </span>
            </template>
        </span>
    </template>
</span>
