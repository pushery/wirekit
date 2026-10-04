{{-- optimistic-ui: n/a — sub-component
     A partial of cart-item; the remove event it sends is the application's to answer. --}}
{{-- The remove control of a cart line, shared by both layouts of cart-item.blade.php and
     rendered in its scope. --}}
@if(isset($remove) && $remove->hasActualContent())
    {{ $remove }}
@elseif($removable && ! $readonly)
    {{-- `x-data` on the trigger itself, because Alpine never installs a handler on an
         element with no scope and the failure is silent — the button looks fine and does
         nothing. The accessible name carries the product, so ten of these in one cart are
         ten different controls to anyone using voice control. --}}
    <span x-data>
        {{-- The glyph goes in `iconLeft` and the label in the default slot, where
             `icon-only` hides it visually and keeps it as the accessible name. `button`
             declares `iconOnly` and not `icon`, so an `icon` attribute would render as a
             plain attribute with no glyph at all, and the alias for this gesture is
             `close`, not a heroicon spelling. That is also why there is no `aria-label`
             here: two names on one control is one too many, and the slot is the one the
             component supports. --}}
        <x-wirekit::button
            intent="neutral"
            surface="ghost"
            :size="$size === 'lg' ? 'md' : 'sm'"
            icon-only
            {{-- `AlpinePayload::from()`, not `Js::from()`. Under the CSP build the
                 latter emits `JSON.parse(…)` for anything non-scalar and `\u` escapes for
                 anything non-ASCII — the evaluator cannot resolve the call, and the
                 tokenizer drops the backslash. Either way the directive would never
                 evaluate and this button would silently do nothing, in the build a
                 CSP-strict application ships. --}}
            x-on:click="$dispatch('wirekit:cart-remove', { item: {{ \Pushery\WireKit\Support\AlpinePayload::from($itemKey) }} })"
        >
            <x-slot:iconLeft>
                <x-wirekit::icon name="close" size="sm" aria-hidden="true" />
            </x-slot:iconLeft>
            {{ $name ? __('wirekit::Remove :name', ['name' => $name]) : __('wirekit::Remove') }}
        </x-wirekit::button>
    </span>
@endif
