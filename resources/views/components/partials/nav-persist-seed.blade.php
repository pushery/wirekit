{{-- optimistic-ui: n/a — sub-component
     Not a component but an @include shared by `sidebar` and `app-rail`. It renders no UI and
     accepts nothing a server could refuse. --}}
{{-- THE FIRST PAINT OF A REMEMBERED COLUMN, and it is the one thing Blade cannot do alone.

     A column with `persist` and the `local` driver stores its state in the reader's browser,
     and no server can read localStorage — so the markup carries the SEED width, the browser
     paints it, and Alpine applies the remembered one a frame later. Reported from several
     production applications as the navigation being briefly collapsed and then snapping open.
     An adopting application measured the same movement at 0.1097 CLS against a budget of 0.1:
     the content column shifting 187px, 53ms in.

     This closes it the only way the platform allows — a script that runs while the parser is
     still working, before anything is painted. It sits immediately after the column's own tag
     and reaches it through `document.currentScript.previousElementSibling`, so it needs no id
     and cannot collide with a second column on the same page.

     ⚠️ EVERY VALUE ARRIVES AS A DATA ATTRIBUTE ON THIS TAG, AND THE BODY IS A CONSTANT.
     That is not a style choice. `AlpinePayload` is the encoder for a directive ATTRIBUTE; in a
     script body HTML escaping does not apply, so a payload carrying the closing-tag sequence
     would end the block and everything after it would parse as markup. That rule is a
     deliberate decision rather than an oversight, and it is machine-held — by
     `CspAuditFindsAnEncoderInAScriptBlockTest`,
     `AlpinePayloadContextGuardTest` and `OwnViewsCspGrammarTest` — and this partial shipped in
     the wrong shape first and was caught by all three at once. Attributes go through Blade's
     ordinary escaping, `dataset` hands them back as plain strings, and the body then has no
     interpolation left to get wrong.

     ⚠️ IT IS A CORRECTION AND NEVER A REQUIREMENT. Blocked by a policy, thrown by a browser
     with storage switched off, or simply never reached, the column keeps exactly the behavior
     it has today: Alpine reads the same value and applies the same class one frame later. That
     is what makes it safe to ship unconditionally — the failure mode is the status quo.

     The nonce is why the objection recorded in `app-rail`'s own prop comment no longer holds.
     It said an inline script is what a strict `script-src 'self'` policy blocks, which is true
     of an UNNONCED one; `fonts.blade.php` already answers the same objection the same way for
     its inline <style>, and this reads the identical helper.

     Params: $seedKey, $seedOn (the boolean the server rendered), $seedClassOn, $seedClassOff,
     and $seedMinWidth (a width the state is gated on, or null). --}}
@php
    $seedNonce = \Pushery\WireKit\WireKit::cspNonce();
@endphp
<script
    @if($seedNonce) nonce="{{ $seedNonce }}" @endif
    data-wk-seed-key="{{ $seedKey }}"
    data-wk-seed-on="{{ $seedOn ? '1' : '0' }}"
    data-wk-seed-class-on="{{ $seedClassOn }}"
    data-wk-seed-class-off="{{ $seedClassOff }}"
    @if($seedMinWidth) data-wk-seed-min="{{ $seedMinWidth }}" @endif
>
(function () {
    var self = document.currentScript;
    var el = self && self.previousElementSibling;
    if (!el) { return; }

    var cfg = self.dataset;
    var stored;
    try { stored = window.localStorage.getItem(cfg.wkSeedKey); }
    catch (e) { return; }
    if (stored === null) { return; }

    var on = stored === '1';

    // The same gate the Alpine factory applies, so the two never disagree for a frame. A rail
    // that only expands above the breakpoint must not be widened here on a phone.
    if (on && cfg.wkSeedMin && typeof window.matchMedia === 'function'
        && !window.matchMedia('(min-width: ' + cfg.wkSeedMin + ')').matches) {
        return;
    }

    if (on === (cfg.wkSeedOn === '1')) { return; }

    // Both names are removed before one is added: the element carries exactly one width
    // utility, and two of equal specificity on one element are resolved by the stylesheet's
    // emission order rather than by state — the failure `sidebar.item` documents for its
    // active foreground, and the reason the Alpine binding beside this uses object syntax.
    el.classList.remove(cfg.wkSeedClassOn, cfg.wkSeedClassOff);
    el.classList.add(on ? cfg.wkSeedClassOn : cfg.wkSeedClassOff);
})();
</script>
