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
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
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
        'info' => 'border-t-[var(--color-wk-accent)]',
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
        'min-w-[280px] max-w-[320px]',
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
    role="listitem"
    @if($isNamed)
        @if($hasCustomHeader)
            aria-label="{{ $label }}"
        @else
            aria-labelledby="{{ $domId }}-label"
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
    @if($hasCustomHeader)
        {{ $header }}
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
    <div
        tabindex="0"
        @if(filled($label)) role="region" aria-label="{{ $label }}" @endif
        class="wk-scrollbar flex flex-col gap-[var(--space-wk-sm,0.5rem)] px-[var(--space-wk-sm,0.5rem)] pb-[var(--space-wk-sm,0.5rem)] overflow-y-auto min-h-[120px] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-offset-[length:var(--ring-wk-offset)] focus-visible:ring-offset-[var(--color-wk-ring-offset)]"
        @if($sortable)
            data-sortable-items
            {{-- The marker used to be the whole feature: three attributes and
                 nothing in the package reading any of them, so `sortable="true"`
                 produced valid markup, no warning, and a board where nothing
                 moved. The behavior lives here now, keyboard path included —
                 a drag-only list is not reorderable by everyone. --}}
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
                ],
            ]) }})"
            x-on:dragstart="dragstart($event)"
            x-on:dragover="dragover($event)"
            x-on:dragend="dragend()"
            x-on:keydown="keydown($event)"
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
