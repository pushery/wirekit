{{-- optimistic-ui: n/a — sub-component
     Not a component but an @include shared by `sidebar` and `app-rail`. It renders no UI and
     accepts nothing a server could refuse. --}}
{{-- The first paint of a remembered column, and it is the one thing Blade cannot do alone.

     A column with `persist` and the `local` driver stores its state in the reader's browser,
     and no server can read localStorage — so the markup carries the seed width, the browser
     paints it, and Alpine applies the remembered one a frame later: the navigation would show
     briefly collapsed and then snap open, shifting the content column and counting against the
     page's layout-shift budget.

     This closes it the only way the platform allows — a script that runs while the parser is
     still working, before anything is painted. It sits immediately after the column's own tag
     and reaches it through `document.currentScript.previousElementSibling`, so it needs no id
     and cannot collide with a second column on the same page.

     Every value arrives as a data attribute on this tag, and the body is a constant.
     That is not a style choice: in a script body HTML escaping does not apply, so a value
     carrying the closing-tag sequence would end the block and everything after it would parse
     as markup. Attributes go through Blade's ordinary escaping, `dataset` hands them back as
     plain strings, and the body then has no interpolation left to get wrong. The two maps of
     markers are JSON the body parses, so they are written with `AlpinePayload::json()`, the
     encoder for a value a script reads back.

     It is a correction and never a requirement. Blocked by a policy, thrown by a browser
     with storage switched off, or simply never reached, it leaves the column as the column is
     without it: Alpine reads the same value and applies the same class one frame later. That
     is what makes it safe to ship unconditionally — the failure mode is the column without
     the seed.

     The nonce answers the objection that an inline script is what a strict
     `script-src 'self'` policy blocks: that is true of an unnonced one, and `fonts.blade.php`
     answers it the same way for its inline <style>, through the identical helper.

     The width is half of the state. The other half is the markers the column's own rules read,
     such as a rail's `data-expanded` and `data-labels` or a sidebar's `data-collapsed`: swapping
     the width alone painted the remembered width with the contents laid out for the other state,
     until Alpine set the markers a frame later. So the markers of each state come along as a map
     of attribute names to values, where `null` removes the attribute.

     Params: $seedKey, $seedOn (the boolean the server rendered), $seedClassOn, $seedClassOff,
     $seedMinWidth (a width the state is gated on, or null), and $seedAttrsOn and $seedAttrsOff
     (the markers of each state, or null). --}}
@php
    $seedNonce = \Pushery\WireKit\WireKit::cspNonce();
    $seedAttrsOn = $seedAttrsOn ?? null;
    $seedAttrsOff = $seedAttrsOff ?? null;
@endphp
<script
    @if($seedNonce)nonce="{{ $seedNonce }}"@endif
    data-wk-seed-key="{{ $seedKey }}"
    data-wk-seed-on="{{ $seedOn ? '1' : '0' }}"
    data-wk-seed-class-on="{{ $seedClassOn }}"
    data-wk-seed-class-off="{{ $seedClassOff }}"
    @if($seedMinWidth) data-wk-seed-min="{{ $seedMinWidth }}" @endif
    @if($seedAttrsOn !== null) data-wk-seed-attrs-on="{{ \Pushery\WireKit\Support\AlpinePayload::json($seedAttrsOn) }}" @endif
    @if($seedAttrsOff !== null) data-wk-seed-attrs-off="{{ \Pushery\WireKit\Support\AlpinePayload::json($seedAttrsOff) }}" @endif
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

    // And the markers of the same state, so the contents are laid out for the width they get.
    var markers = on ? cfg.wkSeedAttrsOn : cfg.wkSeedAttrsOff;
    if (!markers) { return; }
    try { markers = JSON.parse(markers); }
    catch (e) { return; }
    Object.keys(markers).forEach(function (name) {
        if (markers[name] === null) { el.removeAttribute(name); }
        else { el.setAttribute(name, markers[name]); }
    });
})();
</script>
