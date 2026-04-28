@props([
    'position' => config('wirekit.components.toast-region.position', 'top-right'),
    'duration' => config('wirekit.components.toast-region.duration', 5000),
    'max' => config('wirekit.components.toast-region.max', 5),
    'name' => null, // scoped name — when set, listens on 'wirekit-toast-{name}' instead of global
    'filled' => false, // when true, toast uses full variant background color (like callout)
    'scope' => null, // class-personalization scope — passed to WireKit::resolveClasses
    'eventScope' => null, // CSS selector for DOM-containment event filtering
    // (e.g. '[data-wk-toast-scope]') — when set, only events whose
    // dispatching element is inside an ancestor matching the selector
    // are handled. Useful for "per-section toast surfaces" where multiple
    // toast regions on the same page must not cross-talk.
])

@php
    use Pushery\WireKit\WireKit;

    // Position classes — map human-friendly names to fixed positioning
    $positionClasses = match ($position) {
        'top-left' => 'top-0 left-0 items-start',
        'top-center' => 'top-0 left-1/2 -translate-x-1/2 items-center',
        'top-right' => 'top-0 right-0 items-end',
        'bottom-left' => 'bottom-0 left-0 items-start',
        'bottom-center' => 'bottom-0 left-1/2 -translate-x-1/2 items-center',
        'bottom-right' => 'bottom-0 right-0 items-end',
        default => 'top-0 right-0 items-end',
    };

    // Container: fixed portal, stacks toasts vertically with gap
    $containerClasses = WireKit::resolveClasses('toast-region', 'base', implode(' ', [
        'fixed z-[9999]',
        'flex flex-col gap-3',
        'p-4',
        'pointer-events-none',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    // Individual toast card styling — elevated, bordered, shadowed
    $toastClasses = WireKit::resolveClasses('toast-region', 'toast', implode(' ', [
        'pointer-events-auto',
        'w-80 max-w-[calc(100vw-2rem)]',
        'flex items-start gap-3',
        'px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)]',
        'rounded-[var(--radius-wk-md)]',
        'border-[length:var(--border-wk-width)]',
        'shadow-[var(--shadow-wk-lg)]',
        'text-[length:var(--text-wk-sm)]',
    ]), $scope);

    // Variant color tokens — controls border, background, and icon color.
    // "default" style: light tinted background (10%) like Alert.
    // "filled" style: strong variant background color, white text + icon.
    if ($filled) {
        $variantMap = [
            'success' => [
                'border' => 'border-[var(--color-wk-success)]',
                'bg' => 'bg-[var(--color-wk-success)]',
                'icon' => 'text-[var(--color-wk-accent-fg)]',
                'text' => 'text-[var(--color-wk-accent-fg)]',
                'muted' => 'text-[color-mix(in_srgb,var(--color-wk-accent-fg)_80%,transparent)]',
            ],
            'warning' => [
                'border' => 'border-[var(--color-wk-warning)]',
                'bg' => 'bg-[var(--color-wk-warning)]',
                'icon' => 'text-[var(--color-wk-accent-fg)]',
                'text' => 'text-[var(--color-wk-accent-fg)]',
                'muted' => 'text-[color-mix(in_srgb,var(--color-wk-accent-fg)_80%,transparent)]',
            ],
            'danger' => [
                'border' => 'border-[var(--color-wk-danger)]',
                'bg' => 'bg-[var(--color-wk-danger)]',
                'icon' => 'text-[var(--color-wk-accent-fg)]',
                'text' => 'text-[var(--color-wk-accent-fg)]',
                'muted' => 'text-[color-mix(in_srgb,var(--color-wk-accent-fg)_80%,transparent)]',
            ],
            'info' => [
                'border' => 'border-[var(--color-wk-accent)]',
                'bg' => 'bg-[var(--color-wk-accent)]',
                'icon' => 'text-[var(--color-wk-accent-fg)]',
                'text' => 'text-[var(--color-wk-accent-fg)]',
                'muted' => 'text-[color-mix(in_srgb,var(--color-wk-accent-fg)_80%,transparent)]',
            ],
        ];
    } else {
        $variantMap = [
            'success' => [
                'border' => 'border-[color-mix(in_srgb,var(--color-wk-success)_35%,var(--color-wk-border))]',
                'bg' => 'bg-[color-mix(in_srgb,var(--color-wk-success)_10%,var(--color-wk-bg-elevated))]',
                'icon' => 'text-[var(--color-wk-success)]',
                'text' => 'text-[var(--color-wk-text)]',
                'muted' => 'text-[var(--color-wk-text-muted)]',
            ],
            'warning' => [
                'border' => 'border-[color-mix(in_srgb,var(--color-wk-warning)_35%,var(--color-wk-border))]',
                'bg' => 'bg-[color-mix(in_srgb,var(--color-wk-warning)_10%,var(--color-wk-bg-elevated))]',
                'icon' => 'text-[var(--color-wk-warning)]',
                'text' => 'text-[var(--color-wk-text)]',
                'muted' => 'text-[var(--color-wk-text-muted)]',
            ],
            'danger' => [
                'border' => 'border-[color-mix(in_srgb,var(--color-wk-danger)_35%,var(--color-wk-border))]',
                'bg' => 'bg-[color-mix(in_srgb,var(--color-wk-danger)_10%,var(--color-wk-bg-elevated))]',
                'icon' => 'text-[var(--color-wk-danger)]',
                'text' => 'text-[var(--color-wk-text)]',
                'muted' => 'text-[var(--color-wk-text-muted)]',
            ],
            'info' => [
                'border' => 'border-[color-mix(in_srgb,var(--color-wk-accent)_35%,var(--color-wk-border))]',
                'bg' => 'bg-[color-mix(in_srgb,var(--color-wk-accent)_10%,var(--color-wk-bg-elevated))]',
                'icon' => 'text-[var(--color-wk-accent)]',
                'text' => 'text-[var(--color-wk-text)]',
                'muted' => 'text-[var(--color-wk-text-muted)]',
            ],
        ];
    }

    // Default inline SVG icons per variant (same as Alert for visual consistency)
    $iconMap = [
        'success' => '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />',
        'warning' => '<path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />',
        'danger' => '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />',
        'info' => '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />',
    ];
@endphp

{{-- Toast region: fixed container that stacks toast notifications.
     Mount ONCE per layout, typically in the app shell.
     Toasts are dispatched via: $dispatch('wirekit-toast', { title, message, variant })
     With name prop: $dispatch('wirekit-toast-{name}', { ... }) for scoped regions. --}}
<div
    x-data="wirekitToast({ max: {{ $max }}, duration: {{ $duration }}, name: @js($name), scope: @js($eventScope) })"
    {{ $attributes->class([$containerClasses, $positionClasses]) }}
    role="region"
    aria-label="Notifications"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-transition:enter="transition ease-out duration-[var(--transition-wk-duration)]"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-[var(--transition-wk-duration)]"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0 translate-y-2"
            :role="ariaRole(toast.variant)"
            :aria-live="toast.variant === 'danger' ? 'assertive' : 'polite'"
            aria-atomic="true"
            @mouseenter="pause(toast.id)"
            @mouseleave="resume(toast.id)"
            :class="[
                '{{ $toastClasses }}',
                toast.variant === 'success' ? '{{ $variantMap['success']['border'] }} {{ $variantMap['success']['bg'] }}' : '',
                toast.variant === 'warning' ? '{{ $variantMap['warning']['border'] }} {{ $variantMap['warning']['bg'] }}' : '',
                toast.variant === 'danger' ? '{{ $variantMap['danger']['border'] }} {{ $variantMap['danger']['bg'] }}' : '',
                toast.variant === 'info' || !['success','warning','danger'].includes(toast.variant) ? '{{ $variantMap['info']['border'] }} {{ $variantMap['info']['bg'] }}' : '',
            ]"
        >
            {{-- Variant icon --}}
            <div
                aria-hidden="true"
                class="shrink-0 mt-0.5"
                :class="{
                    '{{ $variantMap['success']['icon'] }}': toast.variant === 'success',
                    '{{ $variantMap['warning']['icon'] }}': toast.variant === 'warning',
                    '{{ $variantMap['danger']['icon'] }}': toast.variant === 'danger',
                    '{{ $variantMap['info']['icon'] }}': toast.variant === 'info' || !['success','warning','danger'].includes(toast.variant),
                }"
            >
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                    <template x-if="toast.variant === 'success'">
                        <g>{!! $iconMap['success'] !!}</g>
                    </template>
                    <template x-if="toast.variant === 'warning'">
                        <g>{!! $iconMap['warning'] !!}</g>
                    </template>
                    <template x-if="toast.variant === 'danger'">
                        <g>{!! $iconMap['danger'] !!}</g>
                    </template>
                    <template x-if="toast.variant === 'info' || !['success','warning','danger'].includes(toast.variant)">
                        <g>{!! $iconMap['info'] !!}</g>
                    </template>
                </svg>
            </div>

            {{-- Content: title + message --}}
            <div class="flex-1 min-w-0">
                <template x-if="toast.title">
                    <div
                        class="font-[number:var(--font-wk-heading-weight)]"
                        :class="{
                            '{{ $variantMap['success']['text'] }}': toast.variant === 'success',
                            '{{ $variantMap['warning']['text'] }}': toast.variant === 'warning',
                            '{{ $variantMap['danger']['text'] }}': toast.variant === 'danger',
                            '{{ $variantMap['info']['text'] }}': toast.variant === 'info' || !['success','warning','danger'].includes(toast.variant),
                        }"
                        x-text="toast.title"
                    ></div>
                </template>
                <div
                    :class="{
                        '{{ $variantMap['success']['muted'] }}': toast.variant === 'success',
                        '{{ $variantMap['warning']['muted'] }}': toast.variant === 'warning',
                        '{{ $variantMap['danger']['muted'] }}': toast.variant === 'danger',
                        '{{ $variantMap['info']['muted'] }}': toast.variant === 'info' || !['success','warning','danger'].includes(toast.variant),
                    }"
                    x-text="toast.message"
                ></div>
            </div>

            {{-- Dismiss button --}}
            <button
                type="button"
                @click="remove(toast.id)"
                aria-label="Dismiss notification"
                class="shrink-0 p-1 -m-1 cursor-pointer rounded-[var(--radius-wk-sm)] {{ $filled ? 'text-[var(--color-wk-accent-fg)] hover:opacity-80' : 'text-[var(--color-wk-text-muted)] hover:text-[var(--color-wk-text)]' }} focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </template>
</div>
