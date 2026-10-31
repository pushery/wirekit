{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    // Error-announcement policy inherited by every WireKit form control nested
    // inside this form.
    //   null  — inherit the global wirekit.a11y.announce_error config (the default)
    //   false — turn the per-control aria-live error region OFF for all controls
    //           inside. Use this when THIS form renders its OWN error summary — a
    //           role="alert" list that announces every message and pulls focus (the
    //           WCAG 3.3.1 pattern) — so the per-field live regions would otherwise
    //           double-announce.
    //   true  — force it ON for all controls inside.
    // A per-control :announce-error still overrides this for that one control.
    'announceErrors' => null,
    // After its first submission the form holds every further one back until the page changes,
    // so a second press sends nothing. For a form Livewire does not send: `wire:submit` has its
    // own loading state, and the script leaves a submission it took over alone.
    'submitOnce' => false,
    // How long a held form waits for the page to change, in milliseconds, before it takes a
    // submission again: an answer that leaves the page as it is, such as a download. `0` waits
    // for the page alone.
    'submitOnceRelease' => 10000,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('form', $attributes->getAttributes());

    // The wrapper carries no chrome of its own by default — it is a real <form>
    // plus an inherited error-announcement policy. resolveClasses still runs so a
    // config/scoped override can add layout classes (e.g. a vertical gap stack).
    $classes = WireKit::resolveClasses('form', 'base', '', $scope);

    // The script reads two attributes, which a plain <form> carries the same way
    // (resources/js/utils/submit-once.js), and an attribute the caller wrote into the bag stands
    // in place of the one written here. A wait below zero is none the script knows, so it is the
    // default, as the script reads one.
    $submitOnce = BooleanProp::from($submitOnce, false, 'form', 'submitOnce');
    $submitOnceRelease = \Pushery\WireKit\Support\NumericProp::int($submitOnceRelease, 10000, 'form', 'submitOnceRelease');
    $submitOnceRelease = $submitOnceRelease >= 0 ? $submitOnceRelease : 10000;
@endphp

{{-- A real <form> element: pass wire:submit / action / method through the
     attribute bag exactly as you would on a native form. Controls inside read
     `announceErrors` via @aware, so one setting governs the whole form's
     error-announcement policy. --}}
<form @if($submitOnce && ! $attributes->has('data-wk-submit-once')) data-wk-submit-once @endif @if($submitOnce && ! $attributes->has('data-wk-submit-once-release')) data-wk-submit-once-release="{{ $submitOnceRelease }}" @endif {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}>
    {{ $slot }}
</form>
