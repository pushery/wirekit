@props([
    'name' => null,
    'dismissible' => config('wirekit.components.alert-dialog.dismissible', false),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Alert Dialog — specialized confirmation dialog for destructive actions.
    // Uses role="alertdialog" (not "dialog") to signal urgency to screen readers.
    // Non-dismissible by default — user must click Cancel or Confirm.
    $titleId = 'wk-alert-dialog-title-' . ($name ?? uniqid());
    $descId = 'wk-alert-dialog-desc-' . ($name ?? uniqid());

    $backdropClasses = WireKit::resolveClasses('alert-dialog', 'backdrop', implode(' ', [
        'fixed inset-0',
        'z-[var(--z-wk-modal)]',
        'bg-[var(--color-wk-overlay)]',
    ]), $scope);

    $containerClasses = WireKit::resolveClasses('alert-dialog', 'container', implode(' ', [
        'fixed inset-0',
        'z-[var(--z-wk-modal)]',
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
    x-data="wirekitAlertDialog({ name: '{{ $name }}', dismissible: {{ $dismissible ? 'true' : 'false' }} })"
    {{ $attributes }}
>
    {{-- Trigger slot — clicking opens the alert dialog --}}
    @isset($trigger)
        <div x-on:click="show()">
            {{ $trigger }}
        </div>
    @endisset

    {{-- Alert dialog overlay and panel — teleported to body --}}
    <template x-teleport="body">
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
                    aria-labelledby="{{ $titleId }}"
                    aria-describedby="{{ $descId }}"
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
