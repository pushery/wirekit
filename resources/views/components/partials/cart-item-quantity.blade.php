{{-- optimistic-ui: n/a — sub-component
     A partial of cart-item; that component carries the decision about its quantity field. --}}
{{-- The quantity of a cart line, shared by both layouts of cart-item.blade.php and rendered in
     its scope: text on a line that is only shown, a number field bound to the caller's model, or
     two buttons that each tell the application about one press. --}}
@if($readonly)
    {{-- The quantity as text. The stepper's own label is what named it before, so the
         same word names it here, for a reader that does not see the column it sits in.
         `hide-quantity` leaves it out altogether, the hidden word with it.

         On its own a number beside a line says nothing about what it counts, so it stands in a
         box of the soft fill a neutral badge takes, as large as the quantity of a stepper of the
         same size and rounded as its buttons are. Literal arms: an assembled class has no rule
         behind it. --}}
    @unless($hideQuantity)
        @php
            $readonlyQuantityClasses = match ($size) {
                'sm' => 'min-h-[var(--size-wk-sm)] min-w-[var(--size-wk-sm)] rounded-[var(--radius-wk-sm)]',
                'md' => 'min-h-[var(--size-wk-md)] min-w-[var(--size-wk-md)] rounded-[var(--radius-wk-md)]',
                default => 'min-h-[var(--size-wk-lg)] min-w-[var(--size-wk-lg)] rounded-[var(--radius-wk-md)]',
            };
        @endphp
        <span data-wk-cart-item-quantity data-wk-cart-item-quantity-cell class="inline-flex shrink-0 items-center justify-center {{ $readonlyQuantityClasses }} bg-[var(--color-wk-bg-soft)] px-[var(--padding-wk-x-sm)] tabular-nums text-[length:var(--text-wk-md)]">
            <span class="sr-only">{{ __('wirekit::Quantity') }} </span>{{ $quantityText }}
        </span>
    @endunless
@elseif($stepperValue === 'buttons')
    {{-- One event per press, and nothing here holds a press back. The application decides what a
         press means, a − on the last piece can take the line out, and answers with the new
         quantity, so two quick presses are two presses even while the first is still out. That
         is why the quantity between the buttons is text rather than a field: it shows what the
         application answered, and nothing here changes it ahead of that answer.

         The quantity is this row's live region in this form, and the line total is not: a press
         moves both, and the reader pressed for the quantity. --}}
    @php
        // The quantity is a target the size of the buttons beside it. Literal arms: an assembled
        // class has no rule behind it.
        //
        // On a touch screen all three take the touch target as their own size, the buttons through
        // `touch-floor` and the quantity through the same `wk-touch-row`, and none of them draws
        // the 44px patch an icon-only button carries otherwise. The three stand 4px apart, and the
        // patch reaches 6px past a 32px button, into the edge of the quantity, where it would take
        // the taps meant for it.
        $quantityTargetClasses = match ($size) {
            'sm' => 'min-h-[var(--size-wk-sm)] min-w-[var(--size-wk-sm)]',
            'md' => 'min-h-[var(--size-wk-md)] min-w-[var(--size-wk-md)]',
            default => 'min-h-[var(--size-wk-lg)] min-w-[var(--size-wk-lg)]',
        };
    @endphp
    <div data-wk-cart-item-stepper data-wk-cart-item-quantity-cell role="group" aria-label="{{ $name ? __('wirekit::Quantity of :name', ['name' => $nameText]) : __('wirekit::Quantity') }}" class="inline-flex shrink-0 items-center gap-[var(--gap-wk-xs)]" x-data>
        {{-- Each button shows its name in a tooltip, on hover and on visible focus. The tooltip
             repeats the name rather than describing the button, so the slot stays its only
             accessible name, and the button itself stays the tab stop. --}}
        <x-wirekit::tooltip :text="$decrementName" :focusable-trigger="false" :describes="false">
            {{-- Each press is a tag of its own, chosen outside the tag: Blade reads no directive
                 among the attributes of a component, and a tag that holds one is never compiled. --}}
            @if($decrementRemovesAtMin && $removeAsks)
                {{-- At the smallest quantity (`removeAtMin`) the press takes the line out, and a line
                     that asks first (`confirmRemove`) asks in its own question. --}}
                <x-wirekit::button
                    intent="neutral"
                    :surface="$stepperSurfaceValue"
                    :size="$size"
                    icon-only
                    touch-floor
                    x-on:click="$dispatch('wirekit-alert-dialog-show', { name: {{ \Pushery\WireKit\Support\AlpinePayload::from($removeQuestion) }} })"
                >
                    <x-slot:iconLeft>
                        <x-wirekit::icon :name="$removeIcon" size="sm" aria-hidden="true" />
                    </x-slot:iconLeft>
                    {{ $decrementName }}
                </x-wirekit::button>
            @elseif($decrementRemovesAtMin)
                <x-wirekit::button
                    intent="neutral"
                    :surface="$stepperSurfaceValue"
                    :size="$size"
                    icon-only
                    touch-floor
                    x-on:click="$dispatch('wirekit:cart-remove', { item: {{ \Pushery\WireKit\Support\AlpinePayload::from($itemKey) }} })"
                >
                    <x-slot:iconLeft>
                        <x-wirekit::icon :name="$removeIcon" size="sm" aria-hidden="true" />
                    </x-slot:iconLeft>
                    {{ $decrementName }}
                </x-wirekit::button>
            @else
                <x-wirekit::button
                    intent="neutral"
                    :surface="$stepperSurfaceValue"
                    :size="$size"
                    icon-only
                    touch-floor
                    :disabled="$atMin"
                    x-on:click="$dispatch('wirekit:cart-decrement', { item: {{ \Pushery\WireKit\Support\AlpinePayload::from($itemKey) }} })"
                >
                    {{-- On a line that leaves at zero, the last press takes the line out, so this −
                         says so with the remove control's glyph and name. The event stays the
                         decrement: the application decides what the press means. --}}
                    <x-slot:iconLeft>
                        <x-wirekit::icon :name="$decrementRemoves ? $removeIcon : 'minus'" size="sm" aria-hidden="true" />
                    </x-slot:iconLeft>
                    {{ $decrementName }}
                </x-wirekit::button>
            @endif
        </x-wirekit::tooltip>
        {{-- Two taps on the quantity, or a double click, dispatch `wirekit:cart-quantity` with the
             line's key, so the application can open an entry of its own for a count that would
             take many presses; one tap does nothing. A screen reader activates with one click and
             cannot make the shortcut, so the application offers the same entry where the keyboard
             reaches it, as a line action. `touch-manipulation` keeps a phone from zooming on the
             second tap, and the digits cannot be selected, so a double click marks nothing. --}}
        <span data-wk-cart-item-quantity aria-live="polite"
            x-wk-double-tap="$dispatch('wirekit:cart-quantity', { item: {{ \Pushery\WireKit\Support\AlpinePayload::from($itemKey) }} })"
            class="wk-touch-row inline-flex items-center justify-center {{ $quantityTargetClasses }} touch-manipulation select-none text-center tabular-nums text-[length:var(--text-wk-md)]">{{ $quantityText }}</span>
        <x-wirekit::tooltip :text="$incrementName" :focusable-trigger="false" :describes="false">
            <x-wirekit::button
                intent="neutral"
                :surface="$stepperSurfaceValue"
                :size="$size"
                icon-only
                touch-floor
                :disabled="$atMax"
                x-on:click="$dispatch('wirekit:cart-increment', { item: {{ \Pushery\WireKit\Support\AlpinePayload::from($itemKey) }} })"
            >
                <x-slot:iconLeft>
                    <x-wirekit::icon name="plus" size="sm" aria-hidden="true" />
                </x-slot:iconLeft>
                {{ $incrementName }}
            </x-wirekit::button>
        </x-wirekit::tooltip>
    </div>
@else
    {{-- No width of our own, and the utility that would set one is deliberately not named:
         Tailwind scans Blade comments too, so writing it would emit the class into the
         compiled CSS with nothing rendering it.

         A fixed box would be a guess, and the stepper's own row is wider at `size="lg"`
         than a box sized for the default, so it would be clipped in every cart line,
         silently, because the overflow sits inside a clipping ancestor rather than on the
         page. The component already knows its size; imposing a second one can only ever
         disagree. --}}
    {{-- At its smallest quantity a line that goes there (`removeAtMin`) keeps − in use: the field
         sends its event there, and this cell asks the line's question, or with `confirmRemove` off
         sends the removal at once. --}}
    <div data-wk-cart-item-quantity-cell class="shrink-0" @if($removesAtMin && $removeAsks) x-data x-on:wirekit:cart-ask-remove="$dispatch('wirekit-alert-dialog-show', { name: {{ \Pushery\WireKit\Support\AlpinePayload::from($removeQuestion) }} })" @elseif($removesAtMin) x-data x-on:wirekit:cart-ask-remove="$dispatch('wirekit:cart-remove', { item: {{ \Pushery\WireKit\Support\AlpinePayload::from($itemKey) }} })" @endif>
        <x-wirekit::number-input
            :align-fields="false"
            :label="$name ? __('wirekit::Quantity of :name', ['name' => $nameText]) : __('wirekit::Quantity')"
            :name="filled($quantityName) ? (string) $quantityName : ''"
            hide-label
            :decrease-label="$decrementName"
            :increase-label="$incrementName"
            :min-action="$removesAtMin ? 'wirekit:cart-ask-remove' : null"
            :min-action-label="$removesAtMin ? $removeName : null"
            :min-action-icon="$removesAtMin ? $removeIcon : null"
            :value="$quantity"
            :min="$min"
            :max="$max"
            :step="$step"
            :size="$size"
            :suffix="$unit"
            {{-- Written against `$attributes` itself: inside a component tag, Blade only reads an
                 echo of `$attributes` as the caller's bag, and any other variable leaves the whole
                 tag uncompiled. --}}
            {{ $attributes->whereStartsWith(['wire:model', 'x-model'])->whereDoesntStartWith('x-modelable') }}
        />
    </div>
@endif
