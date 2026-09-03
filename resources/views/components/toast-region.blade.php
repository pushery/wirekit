{{-- optimistic-ui: n/a — client-only
     Transient display. It announces things that already happened. --}}
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
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('toast-region', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $filled = BooleanProp::from($filled, false);

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

    // Edge offset — keeps the toast stack clear of a fixed app header / nav
    // (or a bottom bar) AND of the device safe-area (notch / home indicator).
    // The region is pinned flush to its edge (top-0 / bottom-0); we add the
    // offset as padding on the ACTIVE edge so the toasts sit inside it without
    // disturbing the other three sides' base `p-4`. Developers set the nav
    // height once via `--space-wk-toast-offset` (default 0px) on their app
    // shell / :root; `env(safe-area-inset-*)` is folded in automatically so a
    // top toast clears the notch and a bottom toast clears the home indicator.
    // Inline style (not a Tailwind arbitrary class) to avoid escaping the
    // nested calc()/env() expression.
    $isTopEdge = str_starts_with($position, 'top');
    $offsetStyle = $isTopEdge
        ? 'padding-top: calc(1rem + var(--space-wk-toast-offset, 0px) + env(safe-area-inset-top, 0px));'
        : 'padding-bottom: calc(1rem + var(--space-wk-toast-offset, 0px) + env(safe-area-inset-bottom, 0px));';

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
        'p-[var(--padding-wk-y-md)]',
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
                'icon' => 'text-[color:var(--color-wk-accent-fg)]',
                'text' => 'text-[color:var(--color-wk-accent-fg)]',
                'muted' => 'text-[color-mix(in_srgb,var(--color-wk-accent-fg)_80%,transparent)]',
            ],
            'warning' => [
                'border' => 'border-[var(--color-wk-warning)]',
                'bg' => 'bg-[var(--color-wk-warning)]',
                'icon' => 'text-[color:var(--color-wk-accent-fg)]',
                'text' => 'text-[color:var(--color-wk-accent-fg)]',
                'muted' => 'text-[color-mix(in_srgb,var(--color-wk-accent-fg)_80%,transparent)]',
            ],
            'danger' => [
                'border' => 'border-[var(--color-wk-danger)]',
                'bg' => 'bg-[var(--color-wk-danger)]',
                'icon' => 'text-[color:var(--color-wk-accent-fg)]',
                'text' => 'text-[color:var(--color-wk-accent-fg)]',
                'muted' => 'text-[color-mix(in_srgb,var(--color-wk-accent-fg)_80%,transparent)]',
            ],
            'info' => [
                'border' => 'border-[var(--color-wk-accent)]',
                'bg' => 'bg-[var(--color-wk-accent)]',
                'icon' => 'text-[color:var(--color-wk-accent-fg)]',
                'text' => 'text-[color:var(--color-wk-accent-fg)]',
                'muted' => 'text-[color-mix(in_srgb,var(--color-wk-accent-fg)_80%,transparent)]',
            ],
        ];
    } else {
        $variantMap = [
            'success' => [
                'border' => 'border-[color-mix(in_srgb,var(--color-wk-success)_35%,var(--color-wk-border))]',
                'bg' => 'bg-[color-mix(in_srgb,var(--color-wk-success)_10%,var(--color-wk-bg-elevated))]',
                'icon' => 'text-[color:var(--color-wk-success)]',
                'text' => 'text-[color:var(--color-wk-text)]',
                'muted' => 'text-[color:var(--color-wk-text-muted)]',
            ],
            'warning' => [
                'border' => 'border-[color-mix(in_srgb,var(--color-wk-warning)_35%,var(--color-wk-border))]',
                'bg' => 'bg-[color-mix(in_srgb,var(--color-wk-warning)_10%,var(--color-wk-bg-elevated))]',
                'icon' => 'text-[color:var(--color-wk-warning)]',
                'text' => 'text-[color:var(--color-wk-text)]',
                'muted' => 'text-[color:var(--color-wk-text-muted)]',
            ],
            'danger' => [
                'border' => 'border-[color-mix(in_srgb,var(--color-wk-danger)_35%,var(--color-wk-border))]',
                'bg' => 'bg-[color-mix(in_srgb,var(--color-wk-danger)_10%,var(--color-wk-bg-elevated))]',
                'icon' => 'text-[color:var(--color-wk-danger)]',
                'text' => 'text-[color:var(--color-wk-text)]',
                'muted' => 'text-[color:var(--color-wk-text-muted)]',
            ],
            'info' => [
                'border' => 'border-[color-mix(in_srgb,var(--color-wk-accent)_35%,var(--color-wk-border))]',
                'bg' => 'bg-[color-mix(in_srgb,var(--color-wk-accent)_10%,var(--color-wk-bg-elevated))]',
                'icon' => 'text-[color:var(--color-wk-accent-text)]',
                'text' => 'text-[color:var(--color-wk-text)]',
                'muted' => 'text-[color:var(--color-wk-text-muted)]',
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
    x-data="wirekitToast({ max: {{ $max }}, duration: {{ $duration }}, name: {{ \Pushery\WireKit\Support\AlpinePayload::from($name) }}, scope: {{ \Pushery\WireKit\Support\AlpinePayload::from($eventScope) }} })"
    {{ $attributes->merge(['style' => $offsetStyle])->class([$containerClasses, $positionClasses]) }}
    role="region"
    aria-label="{{ __('wirekit::Notifications') }}"
>
    {{-- The announcement, separated from the toast that caused it.

         A live region must exist BEFORE the text it announces — an element created
         together with its message is a new node, not a region that changed, and
         assistive technology says nothing. The per-toast binding this replaces had
         never announced a single toast.

         Two regions rather than one, because urgency belongs to the region: an
         aria-live value that flips on an existing region is not reliably re-read.
         They are visually hidden and carry no role, so they add nothing to the
         visible stack. --}}
    <div class="sr-only" aria-live="polite" aria-atomic="true" x-text="politeMessage"></div>
    <div class="sr-only" aria-live="assertive" aria-atomic="true" x-text="assertiveMessage"></div>

    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-transition:enter="transition ease-out duration-[var(--transition-wk-duration)]"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-[var(--transition-wk-duration)]"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0 translate-y-2"
            {{-- NO live role and no aria-live on the toast itself.

                 Not just aria-live: `role="alert"` carries an implicit
                 `aria-live="assertive"`, so leaving the role here would put the
                 element back in the announcement path — created inside x-for
                 together with its text, which announces nothing, while the regions
                 above announce correctly. Best case that is dead markup; worst case
                 an assistive technology reads both and the user hears it twice.

                 The visible toast is content. It is reachable and readable like any
                 other content; the transient announcement is the region's job.

                 focusin/focusout alongside mouseenter/mouseleave — a keyboard user
                 who tabs to a toast's action watched it disappear mid-reach, because
                 only the pointer paused the auto-dismiss. --}}
            {{-- A stable hook for the toast element. It used to be findable by its
                 live role, and removing that role — correctly — left nothing to
                 select it by, which is how two browser tests came to look for an
                 element that no longer existed. --}}
            data-wk-toast
            @mouseenter="pause(toast.id)"
            @mouseleave="resume(toast.id)"
            @focusin="pause(toast.id)"
            @focusout="resume(toast.id)"
            :class="[
                {{ \Pushery\WireKit\Support\AlpinePayload::string($toastClasses) }},
                toast.variant === 'success' ? {{ \Pushery\WireKit\Support\AlpinePayload::string($variantMap['success']['border'].' '.$variantMap['success']['bg']) }} : '',
                toast.variant === 'warning' ? {{ \Pushery\WireKit\Support\AlpinePayload::string($variantMap['warning']['border'].' '.$variantMap['warning']['bg']) }} : '',
                toast.variant === 'danger' ? {{ \Pushery\WireKit\Support\AlpinePayload::string($variantMap['danger']['border'].' '.$variantMap['danger']['bg']) }} : '',
                toast.variant === 'info' || !['success','warning','danger'].includes(toast.variant) ? {{ \Pushery\WireKit\Support\AlpinePayload::string($variantMap['info']['border'].' '.$variantMap['info']['bg']) }} : '',
            ]"
        >
            {{-- Variant icon --}}
            <div
                aria-hidden="true"
                class="shrink-0 mt-0.5"
                :class="{
                    {{ \Pushery\WireKit\Support\AlpinePayload::string($variantMap['success']['icon']) }}: toast.variant === 'success',
                    {{ \Pushery\WireKit\Support\AlpinePayload::string($variantMap['warning']['icon']) }}: toast.variant === 'warning',
                    {{ \Pushery\WireKit\Support\AlpinePayload::string($variantMap['danger']['icon']) }}: toast.variant === 'danger',
                    {{ \Pushery\WireKit\Support\AlpinePayload::string($variantMap['info']['icon']) }}: toast.variant === 'info' || !['success','warning','danger'].includes(toast.variant),
                }"
            >
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                    {{-- SVG namespace gotcha: <template> elements inside <svg>
                         are NOT treated as HTML template elements (no .content
                         property). Alpine's x-if then crashes with
                         "Cannot read properties of undefined (reading 'cloneNode')"
                         on every page load — even with toasts:[]. Use <g x-show>
                         instead: x-show only toggles display, no template cloning,
                         no SVG-namespace pitfalls. --}}
                    <g x-show="toast.variant === 'success'">{!! $iconMap['success'] !!}</g>
                    <g x-show="toast.variant === 'warning'">{!! $iconMap['warning'] !!}</g>
                    <g x-show="toast.variant === 'danger'">{!! $iconMap['danger'] !!}</g>
                    <g x-show="toast.variant === 'info' || !['success','warning','danger'].includes(toast.variant)">{!! $iconMap['info'] !!}</g>
                </svg>
            </div>

            {{-- Content: title + message --}}
            <div class="flex-1 min-w-0">
                <template x-if="toast.title">
                    <div
                        class="font-[number:var(--font-wk-heading-weight)]"
                        :class="{
                            {{ \Pushery\WireKit\Support\AlpinePayload::string($variantMap['success']['text']) }}: toast.variant === 'success',
                            {{ \Pushery\WireKit\Support\AlpinePayload::string($variantMap['warning']['text']) }}: toast.variant === 'warning',
                            {{ \Pushery\WireKit\Support\AlpinePayload::string($variantMap['danger']['text']) }}: toast.variant === 'danger',
                            {{ \Pushery\WireKit\Support\AlpinePayload::string($variantMap['info']['text']) }}: toast.variant === 'info' || !['success','warning','danger'].includes(toast.variant),
                        }"
                        x-text="toast.title"
                    ></div>
                </template>
                <div
                    :class="{
                        {{ \Pushery\WireKit\Support\AlpinePayload::string($variantMap['success']['muted']) }}: toast.variant === 'success',
                        {{ \Pushery\WireKit\Support\AlpinePayload::string($variantMap['warning']['muted']) }}: toast.variant === 'warning',
                        {{ \Pushery\WireKit\Support\AlpinePayload::string($variantMap['danger']['muted']) }}: toast.variant === 'danger',
                        {{ \Pushery\WireKit\Support\AlpinePayload::string($variantMap['info']['muted']) }}: toast.variant === 'info' || !['success','warning','danger'].includes(toast.variant),
                    }"
                    x-text="toast.message"
                ></div>
            </div>

            {{-- Dismiss button --}}
            <button
                type="button"
                @click="remove(toast.id)"
                aria-label="{{ __('wirekit::Dismiss notification') }}"
                class="shrink-0 p-1 -m-1 cursor-pointer rounded-[var(--radius-wk-sm)] {{ $filled ? 'text-[color:var(--color-wk-accent-fg)] hover:opacity-80' : 'text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)]' }} focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </template>
</div>
