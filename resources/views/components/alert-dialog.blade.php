{{-- optimistic-ui: n/a — client-only
     Its state is open state and focus containment. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    'name' => null,
    'dismissible' => config('wirekit.components.alert-dialog.dismissible', false),
    // CSS selector, resolved inside the panel, for the control that should hold
    // focus when the dialog opens. Unset, focus goes to Cancel — the least
    // destructive action, per the APG alertdialog pattern.
    'initialFocus' => null,
    // CSS selector for where focus should land when the dialog closes and its own
    // trigger is gone — the normal case for a delete-in-a-list confirmation, whose
    // Livewire re-render removes the very row that held the trigger. Unset, the
    // dialog falls back to the nearest ancestor of the trigger that survived.
    'focusReturnTo' => null,
    'scope' => null,
    // An explicit accessible name, for an alert-dialog composed WITHOUT
    // `alert-dialog.title`. Same reasoning as `drawer`: `aria-labelledby` points at an id
    // the title would have bound at runtime, and a caller `aria-label` never reaches the
    // element that carries the role. WCAG 2.1 4.1.2, Level A.
    'label' => null,
    // `false` drops `aria-describedby` for an alert-dialog composed without
    // `alert-dialog.description`, where the attribute otherwise ships a permanently
    // unresolvable reference. Default `null` keeps the documented behavior, so nothing
    // that composes the description changes.
    'describedby' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('alert-dialog', $attributes->getAttributes());

    // Alert Dialog — specialized confirmation dialog for destructive actions.
    // Uses role="alertdialog" (not "dialog") to signal urgency to screen readers.
    // Non-dismissible by default — user must click Cancel or Confirm.
    $titleId = 'wk-alert-dialog-title-' . ($name ?? \Illuminate\Support\Str::random(12));
    $descId = 'wk-alert-dialog-desc-' . ($name ?? \Illuminate\Support\Str::random(12));

    // A caller-supplied `aria-label` names the DIALOG, not the wrapper it was landing on.
    // `{{ $attributes }}` sits on the roleless outer element, so `<x-wirekit::alert-dialog
    // aria-label="…">` rendered a name on something no assistive technology reads as a
    // dialog — WCAG 4.1.2. It is pulled out here and applied to the panel below, where
    // `label` already goes; the two are the same intent spelled two ways, so `label` wins
    // when both are given rather than emitting a conflicting pair.
    $callerLabel = $attributes->get('aria-label');
    $attributes = $attributes->except(['aria-label']);

    $backdropClasses = WireKit::resolveClasses('alert-dialog', 'backdrop', implode(' ', [
        'wk-overlay-fixed fixed inset-0',
        'wk-overlay-layer-modal z-[var(--z-wk-modal)]',
        'bg-[var(--color-wk-overlay)]',
    ]), $scope);

    $containerClasses = WireKit::resolveClasses('alert-dialog', 'container', implode(' ', [
        'wk-overlay-fixed fixed inset-0',
        'wk-overlay-layer-modal z-[var(--z-wk-modal)]',
        'flex items-center justify-center',
        'p-[var(--padding-wk-y-xl)]',
        'wk-scrollbar overflow-y-auto',
    ]), $scope);

    $panelClasses = WireKit::resolveClasses('alert-dialog', 'panel', implode(' ', [
        'relative w-full',
        'max-w-[var(--size-wk-modal-sm)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-xl)]',
        'shadow-[var(--shadow-wk-lg)]',
        'overflow-hidden',
        // Padding matching modal body — ensures consistent spacing between dialog types.
        'px-[var(--padding-wk-x-xl)]',
        'py-[var(--padding-wk-y-xl)]',
    ]), $scope);
@endphp

<div
    x-data="wirekitAlertDialog({ name: {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $name) }}, dismissible: {{ $dismissible ? 'true' : 'false' }}, initialFocus: {{ \Pushery\WireKit\Support\AlpinePayload::from($initialFocus) }}, focusReturnTo: {{ \Pushery\WireKit\Support\AlpinePayload::from($focusReturnTo) }} })"
    {{ $attributes }}
>
    {{-- Trigger slot — clicking opens the alert dialog --}}
    @isset($trigger)
        <div x-on:click="show()">
            {{ $trigger }}
        </div>
    @endisset

    {{-- Alert dialog overlay and panel — teleported to body --}}
    <template x-teleport="#wk-overlay-root">
        <div x-show="open" x-cloak>
            {{-- Backdrop --}}
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="{{ $backdropClasses }}"
                @if($dismissible) x-on:click="handleBackdropClick()" @endif
                aria-hidden="true"
            ></div>

            {{-- Dialog container — click handler on container (not backdrop)
                 because this div is layered on top and intercepts pointer events.
                 Panel has x-on:click.stop so clicks inside don't bubble. --}}
            <div
                class="{{ $containerClasses }}"
                @if($dismissible) x-on:click="handleBackdropClick()" @endif
            >
                <div
                    x-ref="panel"
                    x-show="open"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    role="alertdialog"
                    aria-modal="true"
                    @if($label || $callerLabel)
                        aria-label="{{ $label ?: $callerLabel }}"
                    @else
                        aria-labelledby="{{ $titleId }}"
                    @endif
                    @if($describedby !== false) aria-describedby="{{ $descId }}" @endif
                    class="{{ $panelClasses }}"
                    x-on:click.stop
                    data-wk-title-id="{{ $titleId }}"
                    data-wk-desc-id="{{ $descId }}"
                >
                    {{ $slot }}
                </div>
            </div>
        </div>
    </template>
</div>
