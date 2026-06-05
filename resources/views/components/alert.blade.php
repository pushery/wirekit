@props([
    'variant' => config('wirekit.components.alert.variant', 'info'),
    'title' => null,
    'dismissible' => false,
    'icon' => true,
    // Optional reveal animation. Null = no animation (default, v1.5.0-identical).
    'animateIn' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $animateAttr = WireKit::resolveAnimateIn($animateIn, 'alert');

    // Validate variant against the canonical intent set. 'primary' and 'info'
    // are visual synonyms on alert (both tint with --color-wk-accent — no
    // separate --color-wk-info token exists in the WireKit token surface);
    // 'neutral' is a quiet gray treatment for non-severity-bearing notices.
    $variantValue = match ($variant) {
        'primary', 'neutral', 'info', 'success', 'warning', 'danger' => $variant,
        default => WireKit::validateProp('alert', 'variant', $variant, ['primary', 'neutral', 'info', 'success', 'warning', 'danger']),
    };

    // Base classes: flex layout with icon + content columns, tinted background via color-mix
    $baseClasses = WireKit::resolveClasses('alert', 'base', implode(' ', [
        'relative flex items-start gap-3',
        'px-[var(--padding-wk-x-md)]',
        'py-[var(--padding-wk-y-md)]',
        'rounded-[var(--radius-wk-md)]',
        'border-[length:var(--border-wk-width)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);

    // Variant color tokens — controls icon color, border, and background tint
    // color-mix() produces a subtle tinted background matching the semantic color
    $variantColors = match ($variantValue) {
        'success' => [
            'border' => 'border-[color-mix(in_srgb,var(--color-wk-success)_35%,var(--color-wk-border))]',
            'bg' => 'bg-[color-mix(in_srgb,var(--color-wk-success)_10%,var(--color-wk-bg-elevated))]',
            'icon' => 'text-[color:var(--color-wk-success)]',
        ],
        'warning' => [
            'border' => 'border-[color-mix(in_srgb,var(--color-wk-warning)_35%,var(--color-wk-border))]',
            'bg' => 'bg-[color-mix(in_srgb,var(--color-wk-warning)_10%,var(--color-wk-bg-elevated))]',
            'icon' => 'text-[color:var(--color-wk-warning)]',
        ],
        'danger' => [
            'border' => 'border-[color-mix(in_srgb,var(--color-wk-danger)_35%,var(--color-wk-border))]',
            'bg' => 'bg-[color-mix(in_srgb,var(--color-wk-danger)_10%,var(--color-wk-bg-elevated))]',
            'icon' => 'text-[color:var(--color-wk-danger)]',
        ],
        'primary', 'info' => [
            'border' => 'border-[color-mix(in_srgb,var(--color-wk-accent)_35%,var(--color-wk-border))]',
            'bg' => 'bg-[color-mix(in_srgb,var(--color-wk-accent)_10%,var(--color-wk-bg-elevated))]',
            'icon' => 'text-[color:var(--color-wk-accent)]',
        ],
        'neutral' => [
            'border' => 'border-[var(--color-wk-border)]',
            'bg' => 'bg-[var(--color-wk-bg-muted)]',
            'icon' => 'text-[color:var(--color-wk-text-muted)]',
        ],
        default => [
            'border' => 'border-[var(--color-wk-border)]',
            'bg' => 'bg-[var(--color-wk-bg-elevated)]',
            'icon' => 'text-[color:var(--color-wk-text-muted)]',
        ],
    };

    // ARIA role — "alert" for danger (assertive announcement), "status" otherwise (polite)
    $role = $variantValue === 'danger' ? 'alert' : 'status';

    // Accessible variant label for screen readers (prefix the alert content)
    $variantLabel = match ($variantValue) {
        'success' => 'Success',
        'warning' => 'Warning',
        'danger' => 'Error',
        'primary', 'info' => 'Information',
        'neutral' => 'Notice',
        default => 'Notice',
    };

    // Default inline SVG icons per variant (avoids blade-icons dependency)
    // Simple heroicons-style outline paths, 20x20 viewBox
    $defaultIcon = match ($variantValue) {
        'success' => '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />',
        'warning' => '<path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />',
        'danger' => '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />',
        default => '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />', // primary, info, neutral
    };
@endphp

<div
    @if($dismissible)
        {{-- No x-cloak: starts visible (shown: true), only hides on user dismiss.
             animateIn is silently skipped on dismissible alerts to avoid
             double-x-data conflicts; wrap in <x-wirekit::reveal> instead.
             data-replayable opts the dismissed-then-empty preview into the docs
             site's replay affordance so a reader can restore the alert without a
             full page reload (inert in a developer app — no replay-button there). --}}
        x-data="{ shown: true }"
        x-show="shown"
        x-transition.opacity
        data-replayable="true"
    @elseif($animateAttr)
        {!! $animateAttr !!}
    @endif
    role="{{ $role }}"
    {{ $attributes->class([$baseClasses, $variantColors['border'], $variantColors['bg']]) }}
>
    {{-- Visually hidden variant prefix for screen readers ("Warning: ...") --}}
    <span class="sr-only">{{ $variantLabel }}:</span>

    {{-- — iconSlot named slot wins over variant-derived auto
         icon. The bool $icon prop continues to toggle the auto path off. --}}
    @php $hasIconSlot = isset($iconSlot) && $iconSlot->isNotEmpty(); @endphp
    @if($hasIconSlot)
        <div class="shrink-0 mt-0.5">{{ $iconSlot }}</div>
    @elseif($icon !== false)
        <div @class(['shrink-0 mt-0.5', $variantColors['icon']]) aria-hidden="true">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">{!! $defaultIcon !!}</svg>
        </div>
    @endif

    {{-- Body: title (optional) + message + actions (optional) --}}
    <div class="flex-1 min-w-0">
        @if($title)
            <div class="font-[number:var(--font-wk-heading-weight)] mb-0.5">{{ $title }}</div>
        @endif
        <div class="text-[color:var(--color-wk-text-muted)]">{{ $slot }}</div>
        @isset($actions)
            <div class="mt-2 flex items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>

    {{-- Dismiss button (only when dismissible) — Alpine toggles shown state --}}
    @if($dismissible)
        <button
            type="button"
            @click="shown = false"
            aria-label="Dismiss"
            class="shrink-0 p-1 -m-1 cursor-pointer rounded-[var(--radius-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    @endif
</div>
