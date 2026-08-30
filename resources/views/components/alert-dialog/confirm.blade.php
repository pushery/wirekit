{{-- optimistic-ui: n/a — passthrough
     The wrapper decides whether an activation is allowed to PASS; what the action then does
     comes from the caller through the attribute bag and carries its own semantics. It was
     marked `client-only` first, which the guard refused and was right to: this component
     stands directly in front of somebody else's server action. --}}
@props([
    'scope' => null,
])

{{-- Alert dialog confirm — the destructive control, held back until the phrase matches.

     Use it instead of a bare <x-wirekit::button> when the parent alert-dialog was given a
     `confirmation-phrase`. Without one it is an ordinary wrapper: `confirmAllowed` is true,
     nothing is blocked and nothing is announced, so it is safe to use everywhere.

     The caller's own attributes — `wire:click`, `x-on:click`, a form target — go on this
     component and reach the button inside it.

     Example:
       <x-wirekit::alert-dialog.confirm wire:click="destroy">Delete forever</x-wirekit::alert-dialog.confirm> --}}
@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('alert-dialog.confirm', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    $classes = WireKit::resolveClasses('alert-dialog.confirm', 'base', '', $scope);
@endphp

{{-- data-wk-alert-confirm is the counterpart to data-wk-alert-cancel. The dialog does not
     place initial focus here — that is the whole safety promise of the pattern — but a
     marker makes the destructive control findable by tests and by a caller's own script.

     THE BLOCK IS REAL, and both halves are needed:

       * `x-on:click.capture` and the keydown twin actually REFUSE the activation. A
         control held back only by `aria-disabled` stays clickable, so a stray Enter would
         fire the irreversible action while the dialog still looked guarded.
       * `aria-disabled` rather than `disabled` keeps the control FOCUSABLE, which is the
         only way its `aria-describedby` reason is ever announced. A `disabled` button is
         skipped by the tab order, so a screen-reader user meets a button that does
         nothing and is told nothing about why.

     Capture phase, deliberately: the caller's handler sits on the button INSIDE this
     wrapper, so a bubbling listener here would run after it had already fired. --}}
<div
    data-wk-alert-confirm
    x-on:click.capture="blockUnlessConfirmed($event)"
    x-on:keydown.enter.capture="blockUnlessConfirmed($event)"
    x-on:keydown.space.capture="blockUnlessConfirmed($event)"
    x-bind:aria-disabled="confirmAllowed ? null : 'true'"
    x-bind:aria-describedby="confirmAllowed ? null : $id('alert-confirm-reason')"
    {{ $attributes->class([$classes]) }}
>
    {{-- The reason, in a region that EXISTS from the first render and whose CONTENT
         changes. It was written the obvious way first — wrapped in `x-if` so it appeared
         only while the control was held back — and that is the one shape that cannot work:
         assistive technology announces a region whose content changes, and a node that
         arrives already carrying its message is announced by nobody. Caught by the guard
         built for exactly this. --}}
    <span
        x-bind:id="$id('alert-confirm-reason')"
        class="sr-only"
        role="status"
        x-text="confirmAllowed ? '' : {{ \Pushery\WireKit\Support\AlpinePayload::from(__('Type the confirmation phrase to enable this action.')) }}"
    ></span>

    @if(trim((string) $slot) === '')
        <x-wirekit::button intent="danger" surface="filled">{{ __('Confirm') }}</x-wirekit::button>
    @elseif(str_contains((string) $slot, '<x-wirekit'))
        {{-- Caller supplied a full WireKit component — use it verbatim, the way
             alert-dialog.cancel does. --}}
        {{ $slot }}
    @else
        {{-- Plain text slot — wrap it so keyboard and screen-reader semantics stay
             correct rather than shipping a bare span somebody has to click. --}}
        <x-wirekit::button intent="danger" surface="filled">{{ $slot }}</x-wirekit::button>
    @endif
</div>
