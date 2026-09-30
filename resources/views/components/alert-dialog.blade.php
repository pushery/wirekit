{{-- optimistic-ui: n/a — client-only
     Its state is open state and focus containment. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    'name' => null,
    'dismissible' => config('wirekit.components.alert-dialog.dismissible', false),
    // Whether opening locks the page's scroll. False for a dialog that lives inside a page
    // region, such as a preview, where the page around it has to keep scrolling.
    'lockScroll' => true,
    // CSS selector, resolved inside the panel, for the control that should hold
    // focus when the dialog opens. Unset, focus goes to Cancel — the least
    // destructive action, per the APG alertdialog pattern.
    'initialFocus' => null,
    // CSS selector for where focus should land when the dialog closes after its action —
    // the normal case being a delete-in-a-list confirmation, whose Livewire re-render
    // removes the very row that held the trigger. A dismissal (Cancel, Escape, the
    // backdrop) returns to the trigger first while it still exists. Unset, the dialog
    // falls back to the nearest ancestor of the trigger that survived.
    'focusReturnTo' => null,
    'scope' => null,
    // Close the dialog once the destructive action has fired.
    //
    // Default false, and that is back-compat rather than a recommendation: a dialog written
    // before this prop keeps its panel up. Without it a caller writes `x-on:click="close()"`
    // on their own control, which works because `close()` is in this component's Alpine
    // scope, and is exactly the line this prop spares them.
    //
    // `alert-dialog.confirm` is not the same thing, whatever its name suggests. That
    // component is a phrase guard: it refuses an activation until the confirmation string
    // is typed. It fires nothing and closes nothing, so swapping a hand-written
    // `x-on:click="close()"` for it removes the close.
    'closeOnConfirm' => config('wirekit.components.alert-dialog.close-on-confirm', false),
    // The exact string a developer must type before `alert-dialog.confirm` will fire —
    // the brake in front of an action nobody can undo. Unset (the default), nothing about
    // this component changes: no field renders and the confirm control is never held back.
    //
    // The comparison is documented rather than guessed at, because a brake whose rule is
    // invisible is a brake people learn to resent: surrounding whitespace is trimmed,
    // everything else is compared EXACTLY. Case, punctuation and inner spacing all count.
    // Trimming is the one concession, and only because a trailing space arrives from a
    // copy-paste rather than from a decision.
    'confirmationPhrase' => null,
    // An explicit accessible name, for an alert-dialog composed WITHOUT
    // `alert-dialog.title`. Same reasoning as `drawer`: `aria-labelledby` points at an id
    // the title would have bound at runtime, and a caller `aria-label` never reaches the
    // element that carries the role. WCAG 2.1 4.1.2, Level A.
    'label' => null,
    // `false` drops `aria-describedby` for an alert-dialog composed without
    // `alert-dialog.description`, where the attribute otherwise ships a permanently
    // unresolvable reference. Default `null` keeps the documented behavior, so nothing
    // that composes the description changes.
    //
    // Read through BooleanProp below, and that is the whole of this prop's history: the
    // check was a bare `!== false`, so only the BOUND spelling `:describedby="false"`
    // worked. `describedby="false"` compiles to the STRING "false", which is not `false`
    // and is truthy besides, so the unresolvable reference the prop exists to remove
    // shipped anyway — silently, in the spelling a reader is most likely to write.
    'describedby' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('alert-dialog', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `dismissible="false"` would otherwise mean the opposite of what the call site reads as. The
    // prop's default is spelled as a `config()` fallback rather than a literal, which is
    // the only reason the coverage guard did not see it. All three reads are truth tests
    // (the Alpine seed and the two backdrop handlers), so the string turned every one of
    // them back on and a destructive-confirmation dialog dismissed on a backdrop click.
    $dismissible = BooleanProp::from($dismissible, false);
    $lockScroll = BooleanProp::from($lockScroll, true);
    // Same reason as `dismissible` above: an unbound `close-on-confirm="false"` is the string
    // 'false', which is truthy, so the dialog would have kept closing on confirm for a caller
    // who wrote the opposite.
    $closeOnConfirm = BooleanProp::from($closeOnConfirm, config('wirekit.components.alert-dialog.close-on-confirm', false));

    // Default TRUE: an unset `describedby` keeps the documented behavior, and only an
    // explicit false in any spelling drops the attribute. `BooleanProp::from` reads the
    // bound `false`, the string "false", "0" and an empty attribute alike — the three
    // spellings the docs advertise, and each of them has to work.
    $describedbyEnabled = BooleanProp::from($describedby, true);

    // Alert Dialog — specialized confirmation dialog for destructive actions.
    // Uses role="alertdialog" (not "dialog") to signal urgency to screen readers.
    // A backdrop click does not close it by default; Escape always does, so a keyboard
    // reader is never trapped, and a stray click cannot approve the action.
    // Counted, not random, when there is no name. The same dialog renders the same id on the next
    // round trip, so Livewire's morph keeps the heading instead of replacing it, and a heading
    // re-rendered on its own still carries the id the dialog's aria-labelledby names.
    $titleId = $name !== null ? 'wk-alert-dialog-title-' . $name : \Pushery\WireKit\Support\DomId::unique(null, 'wk-alert-dialog-title-');
    $descId = $name !== null ? 'wk-alert-dialog-desc-' . $name : \Pushery\WireKit\Support\DomId::unique(null, 'wk-alert-dialog-desc-');

    // A caller-supplied `aria-label` names the DIALOG, not the wrapper it was landing on.
    // `{{ $attributes }}` sits on the roleless outer element, so `<x-wirekit::alert-dialog
    // aria-label="…">` rendered a name on something no assistive technology reads as a
    // dialog — WCAG 4.1.2. It is pulled out here and applied to the panel below, where
    // `label` already goes; the two are the same intent spelled two ways, so `label` wins
    // when both are given rather than emitting a conflicting pair.
    $callerLabel = $attributes->get('aria-label');
    $attributes = $attributes->except(['aria-label']);

    $backdropClasses = 'wk-overlay-fixed wk-overlay-layer-modal '.WireKit::resolveClasses('alert-dialog', 'backdrop', implode(' ', [
        'fixed inset-0',
        'z-[var(--z-wk-modal)]',
        'bg-[var(--color-wk-overlay)]',
    ]), $scope);

    $containerClasses = 'wk-overlay-fixed wk-overlay-layer-modal wk-scrollbar '.WireKit::resolveClasses('alert-dialog', 'container', implode(' ', [
        'fixed inset-0',
        'z-[var(--z-wk-modal)]',
        'flex items-center justify-center',
        'p-[var(--padding-wk-y-xl)]',
        'overflow-y-auto',
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

    // A caller's `x-ref` belongs to the caller's component, and this bag lands on our root,
    // which would keep it: CallerRef::onRoot() hands it to the root above.
    $attributes = \Pushery\WireKit\Support\CallerRef::onRoot($attributes);
@endphp

<div
    x-data="wirekitAlertDialog({ name: {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $name) }}, dismissible: {{ $dismissible ? 'true' : 'false' }}, initialFocus: {{ \Pushery\WireKit\Support\AlpinePayload::from($initialFocus) }}, focusReturnTo: {{ \Pushery\WireKit\Support\AlpinePayload::from($focusReturnTo) }}, confirmationPhrase: {{ \Pushery\WireKit\Support\AlpinePayload::from($confirmationPhrase) }}, closeOnConfirm: {{ $closeOnConfirm ? 'true' : 'false' }}, lockScroll: {{ $lockScroll ? 'true' : 'false' }} })"
    {{ $attributes }}
>
    {{-- Trigger slot — clicking opens the alert dialog.
         The wrapper only carries the click listener, so it generates no box. As a block it kept a
         button in the slot at its own width inside a container that stretches its children, and
         a caller cannot reach it with a class. On its own `contents` changes nothing a page shows;
         `class="contents"` on the component root is what hands the trigger to the container. --}}
    @isset($trigger)
        <div class="contents" x-on:click="show()">
            {{ $trigger }}
        </div>
    @endisset

    {{-- Alert dialog overlay and panel — teleported to the overlay root --}}
    <template x-teleport="#wk-overlay-root">
        <div x-show="isOpen" x-cloak>
            {{-- Backdrop --}}
            <div
                x-show="isOpen"
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
                    x-show="isOpen"
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
                    @if($describedbyEnabled) aria-describedby="{{ $descId }}" @endif
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
