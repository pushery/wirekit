{{-- optimistic-ui: candidate — the quantity field is the only asynchronous surface here, and
     `number-input` already carries `optimistic`/`optimisticArgs`. Deliberately NOT opted in yet:
     the optimistic contract has to be satisfied first, and a rollback that moves focus or
     announces twice is worse than no optimism at all. --}}
@props([
    // The product name. Also the thing the remove control has to say, which is why it is a prop
    // rather than a slot: ten controls that all announce "Remove" cannot be told apart by anyone
    // using voice control, and a slot cannot be read into an aria-label.
    'name' => null,
    // Thumbnail. `imageAlt` defaults to empty on purpose — the name is already in the row as
    // text, so a repeated alt reads the product twice. Pass one only when the image carries
    // information the name does not.
    'image' => null,
    'imageAlt' => '',
    // Unit price, and the struck-through previous price. Both go straight through to `price`,
    // which already draws the strike AND an sr-only "Previous price" — this component does not
    // re-solve that, and must not.
    'price' => null,
    'compareAt' => null,
    'currency' => config('wirekit.currency', 'USD'),
    'minorUnits' => false,
    // What the line costs at this quantity. The APPLICATION computes it. A cart that multiplies
    // here would own state, and cart state belongs to Livewire rather than to this library —
    // every second application has to take an assumption like that back out.
    'lineTotal' => null,
    // The quantity column. `min` defaults to 1 because a cart line with zero of something is a
    // removal rather than a quantity, and `max` is the stock ceiling when the caller knows it.
    'quantity' => 1,
    'min' => config('wirekit.components.cart-item.min', 1),
    'max' => null,
    'step' => 1,
    // The size of the stepper. `lg` rather than the `md` default, and that is a measured choice:
    // `--size-wk-md` is 2.5rem = 40px, which clears WCAG 2.2 SC 2.5.8 (24px, level AA) with room
    // but misses the 44px of SC 2.5.5 (level AAA) and of platform touch guidance. `--size-wk-lg`
    // is 3rem = 48px and clears both. Raising the token instead would move every component that
    // reads it for one ecommerce case, so the preset carries it and a caller can still override.
    'size' => config('wirekit.components.cart-item.size', 'lg'),
    // A unit suffix for the stepper — "kg", "pair", "m". `number-input` already has `suffix`.
    'unit' => null,
    // Whether the built-in remove control renders. The `remove` slot overrides it entirely.
    'removable' => true,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Fully qualified: this view's
    // imports live in the block below, which does not reach this call.
    \Pushery\WireKit\WireKit::warnUnknownProps('cart-item', $attributes->getAttributes());

    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    $size = WireKit::validateProp('cart-item', 'size', $size, ['sm', 'md', 'lg']);
    $removable = BooleanProp::from($removable, true);
    $minorUnits = BooleanProp::from($minorUnits, false);

    // A stable identity for the row, so the remove dispatch says WHICH line and a Livewire
    // re-render keeps the quantity field's DOM. Derived from the name when the caller gives no
    // id, because `uniqid()` would differ between two renders of the same page and the overlay
    // identifiers of eight components were repaired for exactly that reason in v2.54.0.
    $itemKey = $attributes->get('id') ?: 'wk-cart-item-'.substr(md5((string) ($name ?? '')), 0, 8);

    // A sale is a compare-at that is actually higher. Passing a lower or equal one through would
    // draw a strike over a number that never came down, which reads as a discount that is not
    // one. Same guard `product-card` applies, and the two must not disagree.
    $onSale = $compareAt !== null && $price !== null && (float) $compareAt > (float) $price;

    $classes = WireKit::resolveClasses('cart-item', 'base', implode(' ', [
        // \u26a0 `flex-wrap` plus the container query on the controls below, and the numbers are
        // why. A cart line has five columns \u2014 thumbnail, name, stepper, total, remove \u2014 and
        // on a 369px list the controls cluster on the trailing side measured 262px while the NAME collapsed to
        // 19px. `flex-1 min-w-0` gives way to `shrink-0`, so the column that matters is the one
        // that disappears. Below the threshold the cluster takes a row of its own instead.
        'flex flex-wrap items-start gap-[var(--gap-wk-md)]',
        'py-[var(--padding-wk-y-md)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);

    $nameClasses = WireKit::resolveClasses('cart-item', 'name', implode(' ', [
        'font-[number:var(--font-wk-heading-weight)]',
        'text-[length:var(--text-wk-md)]',
    ]), $scope);

    $metaClasses = WireKit::resolveClasses('cart-item', 'meta', implode(' ', [
        'text-[length:var(--text-wk-sm)]',
        'text-[color:var(--color-wk-text-muted)]',
    ]), $scope);
@endphp

<li data-wk-prose-skip
    data-wk-cart-item
    id="{{ $itemKey }}"
    {{ $attributes->except('id')->class([$classes]) }}
>
    @if($image)
        <div class="shrink-0 w-16">
            <x-wirekit::image :src="$image" :alt="$imageAlt" ratio="1/1" fit="cover" rounded="md" />
        </div>
    @endif

    <div class="flex min-w-0 flex-1 flex-col gap-[var(--gap-wk-xs)]">
        @if($name)
            <span class="{{ $nameClasses }}">{{ $name }}</span>
        @endif

        {{-- The variant line — "Size M · Blue". A slot rather than a prop because it is display
             copy the application composes, and because a prop would invite a single string where
             two facts belong. --}}
        @if($slot->hasActualContent())
            <span class="{{ $metaClasses }}">{{ $slot }}</span>
        @endif

        @if($price !== null)
            <span data-wk-cart-item-unit-price>
                <x-wirekit::price
                    :amount="$price"
                    :base="$onSale ? $compareAt : null"
                    :currency="$currency"
                    :minor-units="$minorUnits"
                    size="sm"
                />
            </span>
        @endif
    </div>

    {{-- `@max-lg` is 32rem = 512px, which is the arithmetic rather than a round number: 64px of
         thumbnail plus 262px of controls plus the gaps leaves a name column under 160px on
         anything narrower, and a product name is not a thing to abbreviate. Outside a
         `cart-list` the named container does not exist, so none of these match and the line
         stays on one row \u2014 which is the old behavior, not a broken one. --}}

    {{-- `flex-wrap` on the cluster itself, and it is NOT redundant with the one on the line
         above: that one wraps the cluster away from the name, this one wraps INSIDE the
         cluster. Measured at a 360px viewport inside the documentation preview column, where
         the cluster gets 242px and its three children need 249px before the two gaps are
         counted -- stepper 148, line total 61, remove 40. `justify-between` distributes
         spare width and has none to distribute, so the remove control painted 24px past the
         right edge in BOTH engines, identically. Below the threshold it now takes a row of
         its own. Above it there is room, nothing wraps, and the desktop line is unchanged:
         a wrap only happens where the content already did not fit. --}}
    <div class="flex shrink-0 flex-wrap items-center gap-[var(--gap-wk-md)] @max-lg/wk-cart-list:w-full @max-lg/wk-cart-list:justify-between">
        {{-- No width of our own. \u26a0 AND THE UTILITY THAT USED TO BE HERE IS DELIBERATELY NOT
             NAMED: Tailwind scans Blade COMMENTS too, so writing it would emit the class into
             the compiled CSS with nothing rendering it, and the reverse-diff would report an
             untraceable selector. The note about the trap springs the trap.

             A fixed 112px box was a guess, and the stepper's own row measured
             128px at `size="lg"` \u2014 so the box clipped it by 16px in every cart line, silently,
             because the overflow was inside a clipping ancestor rather than on the page. The
             component already knows its size; imposing a second one can only ever disagree. --}}
        <div class="shrink-0">
            <x-wirekit::number-input
                :label="__('wirekit::Quantity')"
                hide-label
                :value="$quantity"
                :min="$min"
                :max="$max"
                :step="$step"
                :size="$size"
                :suffix="$unit"
                {{ $attributes->only(['wire:model', 'wire:model.live', 'x-model']) }}
            />
        </div>

        {{-- The line total. `aria-live="polite"` sits HERE and on nothing else in the row: a
             quantity change moves this number and the cart's grand total, and announcing both
             speaks twice for one click. The line is the one the person just acted on. --}}
        @if($lineTotal !== null)
            <span data-wk-cart-item-total aria-live="polite" class="min-w-[6ch] text-end">
                <x-wirekit::price
                    :amount="$lineTotal"
                    :currency="$currency"
                    :minor-units="$minorUnits"
                />
            </span>
        @endif

        @if(isset($remove) && $remove->hasActualContent())
            {{ $remove }}
        @elseif($removable)
            {{-- `x-data` on the trigger itself, because Alpine never installs a handler on an
                 element with no scope and the failure is silent — the button looks fine and does
                 nothing. The accessible name carries the product, so ten of these in one cart are
                 ten different controls to anyone using voice control. --}}
            <span x-data>
                {{-- \u26a0 THIS USED TO PASS `icon="x-mark"`, AND BOTH HALVES WERE WRONG. `button`
                     declares `iconOnly` and NOT `icon`, so Blade rendered the name as a plain
                     attribute and the control had no glyph at all \u2014 exactly the failure class the
                     header of this file's test warns about. And `x-mark` is a heroicon spelling,
                     not one of the catalog's aliases; the alias for this gesture is `close`.

                     The documented shape puts the glyph in `iconLeft` and the LABEL in the default
                     slot, where `icon-only` hides it visually and keeps it as the accessible name.
                     That is also why there is no `aria-label` here any more: two names on one
                     control is one too many, and the slot is the one the component supports. --}}
                <x-wirekit::button
                    intent="neutral"
                    surface="ghost"
                    :size="$size === 'lg' ? 'md' : 'sm'"
                    icon-only
                    {{-- \u26a0 `AlpinePayload::from()`, NOT `Js::from()`. Under the CSP build the
                         latter emits `JSON.parse(…)` for anything non-scalar and `\u` escapes for
                         anything non-ASCII — the evaluator cannot resolve the call, and the
                         tokenizer drops the backslash. Either way the directive never evaluates
                         and this button silently does nothing, in the build a CSP-strict
                         application ships. `csp-expression-audit.mjs` holds it, and that audit
                         lives in the ESM chain rather than in Pest: a green PHP suite says
                         nothing about it. --}}
                    x-on:click="$dispatch('wirekit:cart-remove', { item: {{ \Pushery\WireKit\Support\AlpinePayload::from($itemKey) }} })"
                >
                    <x-slot:iconLeft>
                        <x-wirekit::icon name="close" size="sm" aria-hidden="true" />
                    </x-slot:iconLeft>
                    {{ $name ? __('wirekit::Remove :name', ['name' => $name]) : __('wirekit::Remove') }}
                </x-wirekit::button>
            </span>
        @endif
    </div>
</li>
