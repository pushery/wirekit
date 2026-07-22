@props([
    // Error-announcement policy inherited by every WireKit form control nested
    // inside this form (WIRE-204).
    //   null  — inherit the global wirekit.a11y.announce_error config (the default)
    //   false — turn the per-control aria-live error region OFF for all controls
    //           inside. Use this when THIS form renders its OWN error summary — a
    //           role="alert" list that announces every message and pulls focus (the
    //           WCAG 3.3.1 pattern) — so the per-field live regions would otherwise
    //           double-announce.
    //   true  — force it ON for all controls inside.
    // A per-control :announce-error still overrides this for that one control.
    'announceErrors' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // The wrapper carries no chrome of its own by default — it is a real <form>
    // plus an inherited error-announcement policy. resolveClasses still runs so a
    // config/scoped override can add layout classes (e.g. a vertical gap stack).
    $classes = WireKit::resolveClasses('form', 'base', '', $scope);
@endphp

{{-- A real <form> element: pass wire:submit / action / method through the
     attribute bag exactly as you would on a native form. Controls inside read
     `announceErrors` via @aware, so one setting governs the whole form's
     error-announcement policy. --}}
<form {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</form>
