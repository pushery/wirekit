{{-- optimistic-ui: n/a — client-only
     Same: a scroll region made keyboard-operable. Any card move it holds belongs to the developer. --}}
@props([
    'label' => null,
    'count' => null,
    'intent' => 'neutral',
    'limit' => null,
    'sortable' => false,
    // What the application calls this column when a card moves between columns on a
    // `cross-column` board: it arrives as `from.column` / `to.column` in
    // `wirekit:sortable:moved`. Without one the column is named by its position among the
    // board's sortable columns — honest, and only usable by a server that addresses columns
    // positionally, the same rule a card without a `data-sortable-id` follows.
    'columnId' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('kanban-column', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $sortable = BooleanProp::from($sortable, false);

    $intentValue = match ($intent) {
        'neutral', 'primary', 'success', 'warning', 'danger', 'info' => $intent,
        default => WireKit::validateProp('kanban-column', 'intent', $intent, ['neutral', 'primary', 'success', 'warning', 'danger', 'info']),
    };

    $isOverLimit = $limit !== null && $count !== null && $count > $limit;

    // Header accent color based on intent
    $headerAccentClass = match ($intentValue) {
        'primary' => 'border-t-[var(--color-wk-accent)]',
        'success' => 'border-t-[var(--color-wk-success)]',
        'warning' => 'border-t-[var(--color-wk-warning)]',
        'danger' => 'border-t-[var(--color-wk-danger)]',
        'info' => 'border-t-[var(--color-wk-info-tone)]',
        default => 'border-t-[var(--color-wk-border)]',
    };

    // Counted per request, not derived from the label. `md5($label)` gave two columns with
    // the same name the SAME id, and `aria-labelledby` resolves to the first match — so a
    // board with two "Blocked" columns named the second one after the first one's header.
    // A random id would have been unique and worse: it changes on every render, so a
    // Livewire morph replaces the node instead of patching it.
    //
    // `$domId`, not `$columnId`: that name belongs to the `column-id` prop, and an assignment
    // here would write this internal id over the value the application gave its column.
    $domId = \Pushery\WireKit\Support\DomId::unique(null, 'kanban-column-');

    // The name of a list item has to come from something that EXISTS.
    //
    // `aria-labelledby` was emitted unconditionally while the element carrying that id
    // lives only in the DEFAULT header — so every column using the `header` slot pointed
    // at nothing and was announced as an unnamed item. An empty name is the silent kind of
    // failure: the markup is well-formed, the attribute is present, and the reader simply
    // hears "list item".
    //
    // Two shapes, picked by which header renders. The default header owns a real label
    // element, so it is referenced. A custom header is the caller's own markup and carries
    // no id of ours, so the column names itself from its `label` prop instead. Neither is
    // emitted without a label — an attribute that claims a name and delivers a blank one
    // is worse than no attribute at all.
    $hasCustomHeader = isset($header);
    $isNamed = filled($label);

    $baseClasses = WireKit::resolveClasses('kanban-column', 'base', implode(' ', [
        'flex flex-col',
        // At least 280px, or the board's width where that is less: a column in a vertical board
        // on a phone fills the board instead of standing out past it, and a horizontal board
        // narrower than one column shows one column at a time.
        'min-w-[min(280px,100%)] max-w-[320px]',
        'rounded-[var(--radius-wk-lg)]',
        'bg-[var(--color-wk-bg-muted)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border-subtle)]',
        'border-t-2',
        $headerAccentClass,
        'snap-start',
    ]), $scope);
@endphp

<section
    @unless($attributes->has('role')) role="listitem" @endunless
    @if($isNamed)
        @if($hasCustomHeader)
            @unless($attributes->has('aria-label') || $attributes->has('aria-labelledby')) aria-label="{{ $label }}" @endunless
        @else
            @unless($attributes->has('aria-label') || $attributes->has('aria-labelledby')) aria-labelledby="{{ $domId }}-label" @endunless
        @endif
    @endif
    {{-- The marker stays bare without a `column-id`, so a column that does not name itself
         renders exactly as before; the sortable then falls back to the column's position. --}}
    @if($sortable)
        @if(filled($columnId))
            data-sortable-column="{{ $columnId }}"
        @else
            data-sortable-column
        @endif
    @endif
    {{ $attributes->class([$baseClasses]) }}
>
    {{-- Column header. Same flag the naming above branches on, so the two can never
         disagree about which header rendered — which is precisely how the reference and
         the element carrying its id came apart. --}}
    {{-- The Reorder button of a sortable column: pressed, a click picks a card up and the next
         one places it, the way to move a card with a pointer that cannot drag (WCAG 2.5.7). It
         sits outside the card list, so the list holds cards only, and it reaches the list
         through two events: it announces a press to the column, and the list answers with the
         state it shows. On a connected board one press puts every column in the mode. --}}
    @if($hasCustomHeader)
        {{ $header }}
        @if($sortable)
            <div class="flex justify-end px-[var(--padding-wk-x-lg)] pb-[var(--padding-wk-y-xs)]">
                <x-wirekit::button size="xs" intent="neutral" surface="ghost" data-wk-sortable-reorder aria-pressed="false" x-data="{ on: false }" x-on:click="$dispatch('wirekit:sortable:reorder-toggle')" x-on:wirekit:sortable:reorder-state="on = $event.detail.on" x-bind:aria-pressed="on ? 'true' : 'false'">{{ __('wirekit::Reorder') }}</x-wirekit::button>
            </div>
        @endif
    @else
        <div class="flex items-center justify-between px-[var(--space-wk-md,1rem)] py-[var(--space-wk-sm,0.5rem)]">
            <span class="flex items-center gap-[var(--space-wk-sm,0.5rem)]">
                <span
                    id="{{ $domId }}-label"
                    class="text-[length:var(--text-wk-sm)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]"
                >
                    {{ $label }}
                </span>
                @if($count !== null)
                    <x-wirekit::badge size="sm" :intent="$isOverLimit ? 'danger' : 'neutral'">
                        {{ $count }}@if($limit)/{{ $limit }}@endif
                    </x-wirekit::badge>
                @endif
            </span>
            @if($sortable)
                {{-- The negative block margin keeps the header as tall as a column's without the
                     button: it reaches into the header's own padding. --}}
                <x-wirekit::button size="xs" intent="neutral" surface="ghost" class="-my-[var(--space-wk-xs,0.25rem)]" data-wk-sortable-reorder aria-pressed="false" x-data="{ on: false }" x-on:click="$dispatch('wirekit:sortable:reorder-toggle')" x-on:wirekit:sortable:reorder-state="on = $event.detail.on" x-bind:aria-pressed="on ? 'true' : 'false'">{{ __('wirekit::Reorder') }}</x-wirekit::button>
            @endif
        </div>
    @endif

    {{-- Column body (card items) — focusable scroll region (WCAG 2.1.1).
         Generic scroll container with no composite-widget role, so we annotate it directly:
         tabindex="0" lets keyboard users scroll the column when the cards inside have no other
         focusable element. That half is unconditional.

         The LANDMARK half is not. It fell back to "Column items", so a six-column board was six
         rotor entries with one name — axe reports that as `landmark-unique`, and the name meant
         to tell the columns apart was what made them identical. A named column exposes its
         body under its OWN label; an unnamed one stays reachable and simply is not a
         destination. --}}
    {{-- The body clips on both axes and its first card stands flush with its top, so a card's
         focus outline, which stands off the card by the ring offset, would lie wholly above what
         the body shows. The top padding is as tall as that outline reaches and the negative margin takes
         it back from the header's own padding, so the first card stays where it was; the scroll
         padding stops a card the focus scrolls into view as far short of either edge. The sides
         and the bottom already have more padding than that. The body's own ring is drawn inside:
         the first column stands flush with the board's scrolling strip, which cut an outer ring by
         the reach of its offset. --}}
    <div
        tabindex="0"
        @if(filled($label)) role="region" aria-label="{{ $label }}" @endif
        class="wk-scrollbar flex flex-col gap-[var(--space-wk-sm,0.5rem)] px-[var(--space-wk-sm,0.5rem)] pb-[var(--space-wk-sm,0.5rem)] -mt-[calc(var(--ring-wk-width)_+_var(--ring-wk-offset))] pt-[calc(var(--ring-wk-width)_+_var(--ring-wk-offset))] scroll-py-[calc(var(--ring-wk-width)_+_var(--ring-wk-offset))] overflow-y-auto min-h-[120px] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-inset"
        @if($sortable)
            data-sortable-items
            {{-- `sortable` wires the behavior here, keyboard path included,
                 rather than leaving attributes for code that is not there: a
                 drag-only list is not reorderable by everyone. --}}
            {{-- The factory's own docblock says "the component passes the catalog string",
                 and until now this call site passed nothing at all — so every sentence a
                 keyboard reorder writes into the live region came from the English
                 fallbacks meant for a hand-mount. The live region is the ONLY feedback
                 that path produces, so a German board announced "Grabbed. Position 2 of
                 5." and a reader who does not read English got nothing usable out of the
                 whole interaction.

                 They travel as TEMPLATES with `:position` / `:total` rather than as
                 finished sentences, because the numbers are only known in the browser and
                 ":position of :total" is not the word order every language uses. Same
                 shape as the carousel's slide announcement and stream's status messages.

                 The two cross-column sentences travel on every sortable column, and the
                 factory only uses them on a board marked `data-sortable-connected`: the
                 column cannot see its board's props, and a few bytes of payload are cheaper
                 than a second source of truth about whether the board connects. --}}
            x-data="wirekitSortable({{ \Pushery\WireKit\Support\AlpinePayload::from([
                'roleDescription' => __('wirekit::Sortable item'),
                'messages' => [
                    'grabbed' => __('wirekit::Grabbed. Position :position of :total. Use the arrow keys to move it.'),
                    'grabbedAcross' => __('wirekit::Grabbed. Position :position of :total. Use up and down to move it, left and right to change the column.'),
                    'moved' => __('wirekit::Position :position of :total.'),
                    'movedToColumn' => __('wirekit::Moved to :column. Position :position of :total.'),
                    'dropped' => __('wirekit::Dropped at position :position of :total.'),
                    'canceled' => __('wirekit::Reorder canceled. Back at position :position of :total.'),
                    'pickedUp' => __('wirekit::Picked up. Position :position of :total. Click where it goes, or click it again to put it down.'),
                    'reorderOn' => __('wirekit::Reorder mode. Click a card to pick it up, then click where it goes.'),
                    'reorderOff' => __('wirekit::Reorder mode off.'),
                ],
            ]) }})"
            x-on:dragstart="dragstart($event)"
            x-on:dragover="dragover($event)"
            x-on:dragend="dragend()"
            x-on:keydown="keydown($event)"
            {{-- Reorder mode, the way to move a card with clicks alone (WCAG 2.5.7). In the
                 capture phase, so a click picks up or places before a link or a handler inside
                 the card hears it. --}}
            x-on:click.capture="reorderClick($event)"
            x-bind:data-wk-sortable-reordering="reordering"
        @endif
    >
        {{ $slot }}
    </div>

    {{-- Footer slot --}}
    @if(isset($footer))
        <div class="px-[var(--space-wk-md,1rem)] py-[var(--space-wk-sm,0.5rem)] border-t border-[var(--color-wk-border-subtle)]">
            {{ $footer }}
        </div>
    @endif
</section>
