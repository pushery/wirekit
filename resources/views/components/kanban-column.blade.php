@props([
    'label' => null,
    'count' => null,
    'intent' => 'neutral',
    'limit' => null,
    'sortable' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

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

    $columnId = 'kanban-column-' . md5($label ?? uniqid());

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
    aria-labelledby="{{ $columnId }}-label"
    @if($sortable) data-sortable-column @endif
    {{ $attributes->class([$baseClasses]) }}
>
    {{-- Column header --}}
    @if(isset($header))
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
         Generic scroll container with no composite-widget role, so we
         annotate it directly: tabindex="0" lets keyboard users scroll
         the column when the cards inside have no other focusable
         element; role="region" + aria-label exposes the scroll region
         in landmark navigation under the column's own label. --}}
    <div
        tabindex="0"
        role="region"
        aria-label="{{ $label ?? 'Column items' }}"
        class="flex flex-col gap-[var(--space-wk-sm,0.5rem)] px-[var(--space-wk-sm,0.5rem)] pb-[var(--space-wk-sm,0.5rem)] overflow-y-auto min-h-[120px] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-offset-[length:var(--ring-wk-offset)] focus-visible:ring-offset-[var(--color-wk-ring-offset)]"
        @if($sortable) data-sortable-items @endif
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
