{{-- optimistic-ui: n/a — sub-component
     A partial of cart-item; the remove event it sends is the application's to answer. --}}
{{-- The remove control of a cart line, shared by both layouts of cart-item.blade.php and
     rendered in its scope. --}}
@if(\Pushery\WireKit\Support\SlotContent::given($remove ?? null))
    {{-- One element around the caller's control, so it takes the line's remove column, before the quantity. --}}
    <span data-wk-cart-item-remove class="inline-flex shrink-0 items-center">{{ $remove }}</span>
@elseif($builtInRemove)
    {{-- `x-data` on the trigger itself, because Alpine never installs a handler on an
         element with no scope and the failure is silent — the button looks fine and does
         nothing. The accessible name carries the product, so ten of these in one cart are
         ten different controls to anyone using voice control. --}}
    {{-- The yes of the line's question arrives here from the dialog, which stands in the overlay
         root, outside the cart: this control, inside the line, sends the removal, so a listener
         around the list hears it wherever the question was asked from. --}}
    <span data-wk-cart-item-remove x-data @if($removeAsks) x-on:wirekit:cart-remove-confirmed.window="$event.detail.question === {{ \Pushery\WireKit\Support\AlpinePayload::from($removeQuestion) }} && $dispatch('wirekit:cart-remove', { item: {{ \Pushery\WireKit\Support\AlpinePayload::from($itemKey) }} })" @endif>
        {{-- The glyph goes in `iconLeft` and the label in the default slot, where
             `icon-only` hides it visually and keeps it as the accessible name. `button`
             declares `iconOnly` and not `icon`, so an `icon` attribute would render as a
             plain attribute with no glyph at all, and the alias for this gesture is the
             caller's `remove-icon`, `close` unless they name another, never a heroicon
             spelling. That is also why there is no `aria-label`
             here: two names on one control is one too many, and the slot is the one the
             component supports. The tooltip shows the same name to a reader who sees no
             word on the button and describes nothing, so the slot stays the only name. --}}
        <x-wirekit::tooltip as="span" :text="$removeName" :focusable-trigger="false" :describes="false">
            {{-- The size of the stepper, so every control of a line is one size. Beside the
                 stepper of a compact line, 4px before its −, the control takes the touch target as
                 its own size, as the stepper does: the 44px patch of a 32px button reaches 6px past
                 it, into the edge of the −, and the two would share the taps there. In the other
                 layout it stands the wider gap of that layout before the −. --}}
            {{-- `AlpinePayload::from()`, not `Js::from()`. Under the CSP build the latter emits
                 `JSON.parse(…)` for anything non-scalar and `\u` escapes for anything non-ASCII —
                 the evaluator cannot resolve the call, and the tokenizer drops the backslash.
                 Either way the directive would never evaluate and this button would silently do
                 nothing, in the build a CSP-strict application ships. --}}
            {{-- A line that asks first (`confirmRemove`) opens its question, whose yes comes back to
                 the span above. Each press is a tag of its own, chosen outside the tag: Blade reads
                 no directive among the attributes of a component. --}}
            @if($removeAsks)
                <x-wirekit::button
                    intent="neutral"
                    surface="ghost"
                    :size="$size"
                    icon-only
                    :touch-floor="$removeInRow"
                    x-on:click="$dispatch('wirekit-alert-dialog-show', { name: {{ \Pushery\WireKit\Support\AlpinePayload::from($removeQuestion) }} })"
                >
                    <x-slot:iconLeft>
                        <x-wirekit::icon :name="$removeIcon" size="sm" aria-hidden="true" />
                    </x-slot:iconLeft>
                    {{ $removeName }}
                </x-wirekit::button>
            @else
                <x-wirekit::button
                    intent="neutral"
                    surface="ghost"
                    :size="$size"
                    icon-only
                    :touch-floor="$removeInRow"
                    x-on:click="$dispatch('wirekit:cart-remove', { item: {{ \Pushery\WireKit\Support\AlpinePayload::from($itemKey) }} })"
                >
                    <x-slot:iconLeft>
                        <x-wirekit::icon :name="$removeIcon" size="sm" aria-hidden="true" />
                    </x-slot:iconLeft>
                    {{ $removeName }}
                </x-wirekit::button>
            @endif
        </x-wirekit::tooltip>
    </span>
@endif

{{-- The question taking the line out asks (`confirmRemove`), from the remove control and from − at
     the smallest quantity: whether the line goes. Its yes is the removal, sent from the control
     above; its no leaves the line as it is. --}}
@if($removeAsks)
    <x-wirekit::alert-dialog :name="$removeQuestion" :close-on-confirm="true">
        <x-wirekit::alert-dialog.title>{{ $name ? __('wirekit::Remove :name?', ['name' => $nameText]) : __('wirekit::Remove') }}</x-wirekit::alert-dialog.title>
        <x-wirekit::alert-dialog.actions>
            <x-wirekit::alert-dialog.cancel />
            <x-wirekit::button intent="danger" data-wk-dialog-primary x-on:click="$dispatch('wirekit:cart-remove-confirmed', { question: {{ \Pushery\WireKit\Support\AlpinePayload::from($removeQuestion) }} })">{{ __('wirekit::Remove') }}</x-wirekit::button>
        </x-wirekit::alert-dialog.actions>
    </x-wirekit::alert-dialog>
@endif
