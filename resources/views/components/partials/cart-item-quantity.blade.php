{{-- optimistic-ui: n/a — sub-component
     A partial of cart-item; that component carries the decision about its quantity field. --}}
{{-- The quantity of a cart line, shared by both layouts of cart-item.blade.php and rendered in
     its scope: text on a line that is only shown, a number field bound to the caller's model, or
     two buttons that each tell the application about one press. --}}
@if($readonly)
    {{-- The quantity as text. The stepper's own label is what named it before, so the
         same word names it here, for a reader that does not see the column it sits in.
         `hide-quantity` leaves it out altogether, the hidden word with it. --}}
    @unless($hideQuantity)
        <span data-wk-cart-item-quantity class="shrink-0 tabular-nums text-[length:var(--text-wk-md)]">
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
    <div data-wk-cart-item-stepper role="group" aria-label="{{ $name ? __('wirekit::Quantity of :name', ['name' => $name]) : __('wirekit::Quantity') }}" class="inline-flex shrink-0 items-center gap-[var(--gap-wk-xs)]" x-data>
        <x-wirekit::button
            intent="neutral"
            surface="outline"
            :size="$size"
            icon-only
            :disabled="$atMin"
            x-on:click="$dispatch('wirekit:cart-decrement', { item: {{ \Pushery\WireKit\Support\AlpinePayload::from($itemKey) }} })"
        >
            <x-slot:iconLeft>
                <x-wirekit::icon name="minus" size="sm" aria-hidden="true" />
            </x-slot:iconLeft>
            {{ $name ? __('wirekit::Decrease the quantity of :name', ['name' => $name]) : __('wirekit::Decrease :field', ['field' => __('wirekit::Quantity')]) }}
        </x-wirekit::button>
        <span data-wk-cart-item-quantity aria-live="polite" class="min-w-[3ch] text-center tabular-nums text-[length:var(--text-wk-md)]">{{ $quantityText }}</span>
        <x-wirekit::button
            intent="neutral"
            surface="outline"
            :size="$size"
            icon-only
            :disabled="$atMax"
            x-on:click="$dispatch('wirekit:cart-increment', { item: {{ \Pushery\WireKit\Support\AlpinePayload::from($itemKey) }} })"
        >
            <x-slot:iconLeft>
                <x-wirekit::icon name="plus" size="sm" aria-hidden="true" />
            </x-slot:iconLeft>
            {{ $name ? __('wirekit::Increase the quantity of :name', ['name' => $name]) : __('wirekit::Increase :field', ['field' => __('wirekit::Quantity')]) }}
        </x-wirekit::button>
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
    <div class="shrink-0">
        <x-wirekit::number-input
            :label="$name ? __('wirekit::Quantity of :name', ['name' => $name]) : __('wirekit::Quantity')"
            hide-label
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
