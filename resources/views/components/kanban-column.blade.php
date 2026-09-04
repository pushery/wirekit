{{-- optimistic-ui: n/a — client-only
     Same: a scroll region made keyboard-operable. Any card move it holds belongs to the developer. --}}
@props([
    'label' => null,
    'count' => null,
    'intent' => 'neutral',
    'limit' => null,
    'sortable' => false,
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
    $columnId = \Pushery\WireKit\Support\DomId::unique(null, 'kanban-column-');

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
            aria-labelledby="{{ $columnId }}-label"
        @endif
    @endif
    @if($sortable) data-sortable-column @endif
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
                    id="{{ $columnId }}-label"
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
        class="wk-scrollbar flex flex-col gap-[var(--space-wk-sm,0.5rem)] px-[var(--space-wk-sm,0.5rem)] pb-[var(--space-wk-sm,0.5rem)] overflow-y-auto min-h-[120px] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-offset-[length:var(--ring-wk-offset)] focus-visible:ring-offset-[var(--color-wk-ring-offset)]"
        @if($sortable)
            data-sortable-items
            {{-- The marker used to be the whole feature: three attributes and
                 nothing in the package reading any of them, so `sortable="true"`
                 produced valid markup, no warning, and a board where nothing
                 moved. The behavior lives here now, keyboard path included —
                 a drag-only list is not reorderable by everyone. --}}
            x-data="wirekitSortable()"
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
